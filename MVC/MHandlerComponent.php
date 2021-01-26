<?php


namespace Orkester\MVC;

use Orkester\Manager;
use Orkester\Results\MResultNull;
use Psr\Http\Message\ResponseInterface as Response;

use Orkester\Results\MResult;
use Psr\Http\Message\ServerRequestInterface as Request;

class MHandlerComponent extends MHandler
{

    public function handler(Request $request, Response $response, $args): MResult
    {
        $this->init();
        $componentName = ucFirst($args['componentName'] ?? '');
        if ($componentName == '') {
            return new MResultNull;
        }
        $fileComponent = Manager::getAppPath() . "/Application/Components/{$componentName}";
        mtrace('handler component = ' . $fileComponent);
        if (file_exists($fileComponent)) {
            $view = new MView($fileComponent);
            mtrace('HandlerComponent ' . $fileComponent);
            return $view->getResult($request);
        } else {
            return new MResultNull;
        }
    }


}