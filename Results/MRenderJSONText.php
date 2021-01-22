<?php

namespace Orkester\Results;

/**
 * MRenderJSONText.
 * Retorna objeto JSON com o resultado do processamento.
 * Objeto JSON = {'id':'json$Id', 'type' : 'page', 'data' : '$content'} : conteúdo é HTML
 */
class MRenderJSONText extends MResult
{

    public function __construct($content = '')
    {
        mtrace('Executing MRenderJSONText');
        parent::__construct();
        $id = 'json' . uniqid();
        $this->ajax->setResponseType('JSON');
        $this->ajax->setId($id);
        $this->ajax->setType('page');
        $this->ajax->setData($content);
        $this->content = $this->ajax->returnData();
    }

    public function apply($request, $response)
    {
        $this->nocache($response);
        $response->setHeader('Content-type', 'Content-type: application/json; charset=UTF-8');
        $response->setOut($this->content);
    }

}
