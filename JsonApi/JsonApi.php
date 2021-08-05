<?php


namespace Orkester\JsonApi;


use Doctrine\DBAL\Exception\InvalidFieldNameException;
use JsonApiPhp\JsonApi\Error;
use JsonApiPhp\JsonApi\ErrorDocument;
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

    public static function modelFromResource($resource): MModelMaestro
    {
        $conf = Manager::getConf('jsonApi');
        if (empty($conf) || empty($conf['resources'][$resource])) {
            throw new \InvalidArgumentException('Resource not found', 404);
        }
        return new ($conf['resources'][$resource])();
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

    public function get(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $handler = function (MModelMaestro $model) use ($args, $params) {
            return Retrieve::process(
                $model,
                $args['id'] ?? null,
                $args['association'] ?? null,
                $args['relationship'] ?? null,
                $params['fields'] ?? null,
                $params['sort'] ?? null,
                $params['filter'] ?? null,
                $params['page'] ?? null,
                $params['group'] ?? null
            );
        };
        return $this->handle($request, $response, $args, $handler);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        [$entityHandler, $relationshipHandler] =
            match ($request->getMethod()) {
                'POST' => [Update::class . '::post', Update::class . '::postRelationship'],
                'PATCH' => [Update::class . '::patch', Update::class . '::patchRelationship'],
                default => throw new \InvalidArgumentException('Invalid method', 403)
            };
        if (array_key_exists('relationship', $args)) {
            $handler = function(MModelMaestro $model) use ($args, $relationshipHandler) {
                return $relationshipHandler($model, $this->data->data,
                    $args['id'], $args['relationship']);
            };
        }
        else {
            $handler = function(MModelMaestro $model) use ($args, $entityHandler) {
                return $entityHandler($model, $this->data->data, $args['id'] ?? null);
            };
        }
        return $this->handle($request, $response, $args, $handler);
    }

    public function delete(Request $request, Response $response, array $args): Response {
        if (array_key_exists('relationship', $args)) {
            $handler = function(MModelMaestro $model) use ($args) {
                return Delete::deleteRelationship($model, $this->data->data,
                    $args['id'], $args['relationship']);
            };
        }
        else {
            $handler = function(MModelMaestro $model) use ($args) {
                return Delete::deleteEntity($model, $args['id']);
            };
        }
        return $this->handle($request, $response, $args, $handler);
    }

    public static function createError(int $code, string $title, string $detail): Error {
        return new Error(
            new Error\Status($code),
            new Error\Title($title),
            new Error\Detail($detail)
        );
    }

    public function handle(Request $request, Response $response, array $args, callable $handler): Response
    {
        $this->request = $request;
        $this->response = $response;
        $middleware = Manager::getConf('jsonApi')['middleware'];
        $transaction = null;
        try {
            $resource = $args['resource'];
            $model = self::modelFromResource($resource);
            if (!empty($middleware)) {
                ($middleware . '::beforeRequest')($request, $response, $args, $model);
            }
            if (is_null($model)) {
                throw new \InvalidArgumentException("Unknown resource: $resource", 404);
            }
            $transaction = $model->beginTransaction();
            [0 => $content, 1 => $code] = $handler($model);
            $transaction->commit();
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
                ($middleware . '::afterRequest')($content, $code);
            }
            if ($transaction != null && $transaction->inTransaction()) {
                $transaction->rollback();
            }
            return $this->renderObject($content, $code);
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
