<?php


namespace Orkester\JsonApi;


use Doctrine\DBAL\Exception\InvalidFieldNameException;
use JsonApiPhp\JsonApi\DataDocument;
use JsonApiPhp\JsonApi\Error;
use JsonApiPhp\JsonApi\ErrorDocument;
use JsonApiPhp\JsonApi\NullData;
use Orkester\Exception\EOrkesterException;
use Orkester\Exception\ESecurityException;
use Orkester\Exception\EValidationException;
use Orkester\Manager;
use Orkester\MVC\MController;
use Orkester\MVC\MModelMaestro;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class JsonApi extends MController
{
    public static function getResourceInstance($resource)
    {
        $conf = Manager::getConf('jsonApi');
        if (empty($conf) || empty($conf['resources'][$resource])) {
            throw new \InvalidArgumentException('Resource not found', 404);
        }
        return new ($conf['resources'][$resource])();
    }

    public static function modelFromResource($resource): MModelMaestro
    {
        $instance = static::getResourceInstance($resource);
        if ($instance instanceof MModelMaestro) {
            return $instance;
        }
        throw new \InvalidArgumentException('Resource model not found', 404);
    }

    public static function validateAssociation(MModelMaestro $model, object $entity, string $associationName, mixed $associated, bool $throw = false): array
    {
        $validationMethod = 'validate' . $associationName;
        if (method_exists($model, $validationMethod)) {
            $errors = $model->$validationMethod($entity, $associated);
            if ($throw && !empty($errors)) {
                throw new EValidationException($errors);
            }
            return $errors;
        }
        return [];
    }

    public function get(array $args, MModelMaestro $model, Request $request): array
    {
        $params = $request->getQueryParams();
        return Retrieve::process(
            $model,
            $args['id'] ?? null,
            $args['association'] ?? null,
            $args['relationship'] ?? null,
            $params['fields'] ?? null,
            $params['sort'] ?? null,
            $params['filter'] ?? null,
            $params['page'] ?? null,
            $params['limit'] ?? null,
            $params['group'] ?? null,
            $params['include'] ?? null,
            $params['join'] ?? null
        );
    }

    public function post(array $args, MModelMaestro $model): array
    {
        if (array_key_exists('relationship', $args)) {
            return Update::postRelationship($model, $this->data->data,
                $args['id'], $args['relationship']);
        }
        else {
            return Update::post($model, $this->data->data);
        }
    }

    public function patch(array $args, MModelMaestro $model): array
    {
        if (array_key_exists('relationship', $args)) {
            return Update::patchRelationship($model, $this->data->data,
                $args['id'], $args['relationship']);
        }
        else {
            return Update::patch($model, $this->data->data, $args['id']);
        }
    }

    public function delete(array $args, MModelMaestro $model): array
    {
        if (array_key_exists('relationship', $args)) {
            return Delete::deleteRelationship($model, $this->data->data,
                $args['id'], $args['relationship']);
        }
        else {
            return Delete::deleteEntity($model, $args['id']);
        }
    }

    public static function createError(int $code, string $title, string $detail): Error {
        return new Error(
            new Error\Status($code),
            new Error\Title($title),
            new Error\Detail($detail)
        );
    }

    public function handleRequest(Request $request, Response $response, array $args): Response
    {
        $this->request = $request;
        $this->response = $response;
        $middleware = Manager::getConf('jsonApi')['middleware'];
        try {
            if (!empty($middleware)) {
                ($middleware . '::beforeRequest')($request, $args);
            }
            [0 => $content, 1 => $code] = array_key_exists('resource', $args) ?
                $this->handleModel($request, $response, $args) :
                $this->handleService($request, $response, $args);
        } catch(EValidationException $e) {
            $code = 409; //Conflict
            $es = [];
            foreach ($e->errors as $key => $value) {
                array_push($es, static::createError($code, $key, $value));
            }
            $content = new ErrorDocument(...$es);
        } catch(ESecurityException $e) {
            $code = $e->getCode();
            $content = new ErrorDocument(static::createError($code, 'Forbidden', $e->getMessage()));
        } catch(InvalidFieldNameException $e) {
            $code = 400; //Bad request
            $content = new ErrorDocument(
                static::createError($code, 'Bad request', 'Invalid field')
            );
        } catch (\InvalidArgumentException $e) {
            $code = $e->getCode(); //usually Forbidden or NotFound
            $content = new ErrorDocument(
                static::createError($code, 'Bad request', $e->getMessage())
            );
            merror($e->getMessage());
        } catch (\Exception | \Error | EOrkesterException $e) {
            $code = 500;
            $content = new ErrorDocument(
                static::createError($code, 'InternalServerError', '')
            );
            mfatal(get_class($e) . " at " . $e->getFile() . ": " . $e->getLine());
            mfatal($e->getMessage());
        } finally {
            if (!empty($middleware)) {
                ($middleware . '::afterRequest')($content, $code, $args);
            }
            return $this->renderObject($content, $code);
        }
    }

    public function handleModel(Request $request, Response $response, array $args): array
    {
        $transaction = null;
        $middleware = Manager::getConf('jsonApi')['middleware'];
        $method = match ($request->getMethod()) {
            'GET' => 'get',
            'DELETE' => 'delete',
            'POST' => 'post',
            'PATCH' => 'patch',
            default => throw new \InvalidArgumentException('Invalid HTTP method', 400)
        };
        $resource = $args['resource'] ?? '';
        $result = null;
        try {
            $model = self::modelFromResource($resource);
            $transaction = $model->beginTransaction();
            if (!empty($middleware)) {
                ($middleware . '::beforeModelRequest')($resource, $method, $request, $args);
            }
            $result = $this->{$method}($args, $model, $request);
            $transaction->commit();
            return $result;
        } finally {
            ($middleware . '::afterModelRequest')($result, $resource, $method, $args);
            if ($transaction != null && $transaction->inTransaction()) {
                $transaction->rollback();
            }
        }
    }

    public function handleService(Request $request, Response $response, array $args): array
    {
        ['service' => $service, 'action' => $action] = $args;
        $instance = static::getResourceInstance($service);
        if (method_exists($instance, $action)) {
            $content = (object)['data' => $instance->$action()];
            return [$content, 200];
        }
        else {
            throw new \InvalidArgumentException('Service not found', 404);
        }
    }

    public function routeNotFound(Request $request, Response $response): Response
    {
        $this->request = $request;
        $this->response = $response;
        $code = 404;
        return $this->renderObject(
            new ErrorDocument(static::createError($code, 'Endpoint not found', '')),
            $code
        );
    }
}
