<?php

namespace Orkester\Results;

use Orkester\Manager;
use Orkester\UI\MBlade;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class MRenderException extends MResult
{
    public function __construct(\Exception $exception, int $code = 200)
    {
        mtrace('Executing MRenderException');
        parent::__construct($code);
        $templatePath = Manager::getConf('template.path');
        $templateName = Manager::getConf('template.exception');
        $path = Manager::getBasePath("public/{$templatePath}");
        if (file_exists($path . "/{$templateName}.blade.php")) {
            $template = new MBlade([$path]);
        } else {
            $path = Manager::getClassPath() . "/UI/Templates";
            $template = new MBlade([$path]);
            $templateName = 'exception';
        }
        $this->content = $template->fetch($templateName, [
            'code' => $code,
            'message' => $exception->getMessage()
        ]);
    }

    public function apply(Request $request, Response $response): Response
    {
        $body = $response->getBody();
        $body->write($this->content);
        return $response
            ->withHeader('Content-Type', 'text/html')
            ->withStatus($this->code);
    }

}
