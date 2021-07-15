<?php


namespace Orkester\MVC;

use Orkester\Manager;
use Orkester\Results\MResultNull;

use Orkester\Results\MResult;

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
            $this->result = $view->getResult('GET', 'html');
            return $this->result->apply($request, $response);
        } else {
            return new MResultNull;
        }
    }


}