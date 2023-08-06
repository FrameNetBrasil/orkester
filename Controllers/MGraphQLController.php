<?php

namespace Orkester\Controllers;

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
        return $this->send($this->execute(), 200);
    }

    public function execute(): array
    {
        $executor = Manager::getContainer()->get(Executor::class);
        return $executor->execute($this->query, $this->variables);
    }

    public function send(mixed $content, int $httpCode): Response
    {
        $body = $this->response->getBody();
        $body->write(json_encode($content));
        return $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($httpCode);

    }
}
