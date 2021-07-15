<?php
namespace Orkester\Results;

use Orkester\Types\MFile;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * MBrowserFile.
 * Retorno para download de arquivo via browser, numa requisição AJAX.
 * Objeto JSON = {'id':'$filename', 'type' : 'file', 'data' : '$fileURL'}
 */
class MBrowserFile extends MResult
{

    public function __construct(MFile $file)
    {
        mtrace('Executing MBrowserFile');
        parent::__construct();
        $this->content = $file->getURL();
    }

    public function apply(Request $request, Response $response): Response
    {
        $payload = $this->content;
        $body = $response->getBody();
        $body->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }


}

