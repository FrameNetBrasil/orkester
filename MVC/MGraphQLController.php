<?php

namespace Orkester\MVC;

use Orkester\Exception\EGraphQLException;
use Orkester\GraphQL\Executor;
use Orkester\Manager;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class MGraphQLController
{
    protected string $query;
    protected array $variables;

    public function __construct(protected Request $request, protected Response $response)
    {
        $data = Manager::getData();
        $this->query = $data->query;
        $this->variables = $data->variables ?? [];
    }

    public function render(): Response
    {
        $httpCode = 200;
        try {
            $executor = new Executor($this->query, $this->variables);
            $content = $executor->execute();
        } catch(EGraphQLException $e) {
            $content = ['error' => $e->errors];
            $httpCode = 400;
        } catch(\Exception $e) {
            mfatal($e->getMessage());
            $content = ['error' => ['internal_server_error' => '']];
            $httpCode = 400;
        }
        return $this->send(json_encode($content), $httpCode);
    }

    protected function send(string $content, int $httpCode = 200): Response
    {
        $body = $this->response->getBody();
        $body->write($content);
        return $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($httpCode);

    }
}
