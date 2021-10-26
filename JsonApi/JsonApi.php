<?php


namespace Orkester\JsonApi;


use Doctrine\DBAL\Exception\InvalidFieldNameException;
use Orkester\Exception\EDBException;
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
        }
        else if (!Manager::getConf('jsonApi')['allowSkipAuthorization']) {
            $errors = [$associationName => 'Refused'];
        }
        if ($throw && !empty($errors)) {
            throw new EValidationException($errors);
        }
        return $errors ?? [];
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
            if (!$model->existsId($args['id'])) {
                throw new \InvalidArgumentException('Resource id not found', 404);
            }
            $model->saveAssociation($args['id'], $args['relationship'], $this->data->data, true);
            return [(object) [], 204];
        }
        else {
            $create = function($data) use ($model) {
                $attributes = $data['attributes'] ?? [];
                $relationships = $data['relationships'] ?? [];
                return $model->create($attributes, $relationships, validate: true);
            };
            if (array_key_exists(0, $this->data->data)) {
                array_walk($this->data->data, $create);
                return [(object)['data' => []], 204];
            }
            else {
                $entity = $create($this->data->data);
                return [(object)['data' => Retrieve::getResourceObject($model->getClassMap(), (array)$entity)], 200];
            }
        }
    }

    public function patch(array $args, MModelMaestro $model): array
    {
        if (array_key_exists('relationship', $args)) {
            $model->updateAssociation($args['id'], $args['relationship'], $this->data->data, true);
            return [(object) [], 200];
        }
        else {
            if (!$model->existsId($args['id'])) {
                throw new \InvalidArgumentException('Resource id not found', 404);
            }
            $attributes = $this->data->data['attributes'] ?? [];
            $relationships = $this->data->data['relationships'] ?? [];
            $entity = $model->create($attributes, $relationships, $args['id'], validate: true);
            return [(object)['data' => Retrieve::getResourceObject($model->getClassMap(), (array)$entity)], 200];
        }
    }

    public function delete(array $args, MModelMaestro $model): array
    {
        if (array_key_exists('relationship', $args)) {
            if (!$model->existsId($args['id'])) {
                throw new \InvalidArgumentException('Resource id not found', 404);
            }
            $model->deleteAssociation($args['id'], $args['relationship'], $this->data->data, true);
            return [(object) [], 204];
        }
        else {
            $errors = $model->validateDelete($args['id']);
            if (!empty($errors)) {
                throw new EValidationException($errors);
            }
            $model->delete($args['id']);
            return [(object) [], 204];
        }
    }

    public static function createError(int $code, string $title, string $detail): array
    {
        return [
            'status' => $code,
            'title' => $title,
            'detail' => $detail
        ];
    }

    public static function createErrorResponse(array $errors): object
    {
        return (object)["errors" => $errors];
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
            $content = static::createErrorResponse($es);
        } catch(ESecurityException $e) {
            $code = $e->getCode();
            $content = static::createErrorResponse(static::createError($code, 'Forbidden', $e->getMessage()));
        } catch(InvalidFieldNameException | EDBException $e) {
            $code = 400; //Bad request
            $content = static::createErrorResponse(
                static::createError($code, 'Bad request', 'Invalid or missing field')
            );
            merror($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            $code = $e->getCode(); //usually Forbidden or NotFound
            $content = static::createErrorResponse(
                static::createError($code, 'Bad request', $e->getMessage())
            );
            merror(get_class($e) . " at " . $e->getFile() . ": " . $e->getLine());
            merror($e->getMessage());
        } catch (\Exception | \Error | EOrkesterException $e) {
            $code = 500;
            $content = static::createErrorResponse(
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
            if (!$model->authorizeResource($request->getMethod(), $args['id'] ?? null, $args['relationship'] ?? null)) {
                throw new ESecurityException();
            }
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
            $method = new \ReflectionMethod($instance, $action);
            if ($method->isStatic()) {
                throw new \InvalidArgumentException('Service not found', 404);
            }
            Manager::getData()->id = $args['id'] ?? null;
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
            static::createErrorResponse(static::createError($code, 'Endpoint not found', '')),
            $code
        );
    }
}
