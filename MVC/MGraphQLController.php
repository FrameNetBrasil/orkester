<?php

namespace Orkester\MVC;

use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\Exception\EValidationException;
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
        $executor = new Executor($this->query, $this->variables);
        ['data' => $data, 'errors' => $errors, 'serverErrors' => $serverErrors] = $executor->execute();
        if (!empty($serverErrors)) {
            return $this->send(json_encode(['serverErrors' => $serverErrors]), 400);
        } else if (!empty($errors)) {
            return $this->send(json_encode(['errors' => $errors]), 200);
        } else {
            return $this->send(empty($data) ? '' : json_encode(['data' => $data]), 200);
        }
    }

    protected function send(string $content, int $httpCode): Response
    {
        $body = $this->response->getBody();
        $body->write($content);
        return $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($httpCode);

    }
}
