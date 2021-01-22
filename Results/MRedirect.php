<?php

namespace Orkester\Results;

use Orkester\Manager;
use Orkester\Services\Http\MRequest;
use Orkester\Services\Http\MResponse;
use Orkester\Services\Http\MStatusCode;

/**
 * MNotFound.
 * Retorna objeto JSON ou emite header(Location).
 * Objeto JSON = {'id':'$pageName', 'type' : 'redirect', 'data' : '$url'}
 */
class MRedirect extends MResult
{

    public $view;

    public function __construct($view, $content)
    {
        mtrace('Executing MRedirect');
        parent::__construct();
        $this->content = $content;
        $this->view = $view;
    }

    public function apply(MRequest $request, MResponse $response)
    {
        $response->setStatus(MStatusCode::OK);
        try {
            if ($request->isAjax()) {
                $id = 'redirect' . uniqid();
                $this->ajax->setId($id);
                $this->ajax->setType('redirect');
                $this->ajax->setData($this->content);
                $this->ajax->setResponseType('JSON');
                $this->content = $this->ajax->returnData();
            } else {
                $response->setHeader('Location', 'Location:' . $this->content);
            }
        } catch (\Exception $e) {
            Manager::logError($e->getMessage());
        }
    }

}
