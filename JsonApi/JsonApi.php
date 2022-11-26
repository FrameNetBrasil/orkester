<?php

namespace Orkester\JsonApi;

use JetBrains\PhpStorm\ArrayShape;
use Orkester\Exception\EOrkesterException;
use Orkester\Exception\EValidationException;
use Orkester\Manager;
use Orkester\Controllers\MController;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class JsonApi extends MController
{
    public static function getEndpointInstance(string $name)
    {
        $conf = Manager::getConf('api');
        if (empty($conf) || empty($conf['services'][$name])) {
            throw new \InvalidArgumentException('Endpoint not found', 404);
        }
        return new ($conf['services'][$name])();
    }

    #[ArrayShape(['status' => "int", 'title' => "string", 'detail' => "string|array"])]
    public static function createError(int $code, string $title, string|array $detail): array
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
        $middleware = Manager::getConf('api')['middleware'] ?? null;
        try {
            if (!empty($middleware)) {
                ($middleware . '::beforeRequest')($request, $args);
            }
            [0 => $content, 1 => $code] = $this->handleService($request, $response, $args);
        } catch (EValidationException $e) {
            $code = 409; //Conflict
            $es = [];
            foreach ($e->errors as $key => $value) {
                array_push($es, static::createError($code, $key, $value));
            }
            $content = static::createErrorResponse($es);
        } catch (EOrkesterException $e) {
            $code = $e->getCode();
            $content = static::createErrorResponse(static::createError($code, 'Forbidden', $e->getMessage()));
        } catch (EOrkesterException $e) {
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
        } catch (\Exception|\Error|EOrkesterException $e) {
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

    public function handleService(Request $request, Response $response, array $args): array
    {
        ['service' => $service, 'action' => $action] = $args;
        $instance = static::getEndpointInstance($service);
        if (method_exists($instance, $action)) {
            $instance->setRequestResponse($request, $response);
            Manager::getData()->id = Manager::getData()->id ?? $args['id'] ?? null;
            $instance->init();
            $data = (array)Manager::getData();
            $class = $instance::class;
            $arguments = $this->buildArguments($data,$class . '::' . $action);
            $content = (object)['data' => $instance->$action(...$arguments)];
            return [$content, 200];
        } else {
            throw new \InvalidArgumentException('Service not found', 404);
        }
    }

    public function buildArguments(array $arguments, $service)
    {
        $reflectionMethod = new \ReflectionMethod($service);
        $reflectionParameters = $reflectionMethod->getParameters();
        $missingArguments = [];
        $typeMismatch = [];
        $result = [];
        foreach ($reflectionParameters as $reflectionParameter) {
            $result[$reflectionParameter->getName()] = $arguments[$reflectionParameter->getName()] ?? null;
        }
        return $result;
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
