<?php


namespace Orkester\Results;


use Slim\Psr7\Request;
use Slim\Psr7\Response;

class MRenderStatic extends MResult
{

    public function __construct(public string $filePath, public string $contentType)
    {
        parent::__construct();
    }

    public function apply(Request $request, Response $response): Response
    {
        $response = $response->withHeader('Content-Type', $this->contentType);
        if (file_exists($this->filePath)) {
            $response->getBody()->write(file_get_contents($this->filePath));
            return $response->withStatus(200);
        }
        return $response->withStatus(404);
    }
}
