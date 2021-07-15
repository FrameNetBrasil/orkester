<?php


namespace Orkester\MVC;

use Orkester\Manager;
use Orkester\Results\MRenderPage;
use Orkester\Results\MResultNull;
use Orkester\Results\MResult;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class MLoadComponent
{
    public function __invoke(Request $request, Response $response, $args): Response
    {
        $componentName = $args['componentName'];
        if ($componentName == '') {
            return new MResultNull;
        }
        $fileComponent = Manager::getAppPath() . "/UI/Components/{$componentName}.blade.php";
        mtrace('handler component = ' . $fileComponent);
        if (file_exists($fileComponent)) {
            //$view = new MView($fileComponent);
            //mtrace('HandlerComponent ' . $fileComponent);
            //$this->result = $view->getResult('GET', 'html');
            //return $this->result->apply($request, $response);
            $view = new MView();
            $content = $view->component("{$componentName}.blade.php");
            mtrace('HandlerComponent ' . $fileComponent);
            $this->result = new MRenderPage($content);
            return $this->result->apply($request, $response);
        } else {
            return new MResultNull;
        }
    }


}