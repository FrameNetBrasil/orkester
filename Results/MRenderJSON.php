<?php

namespace Orkester\Results;

/**
 * MRenderJSON.
 * Retorna objeto JSON com o resultado do processamento.
 */
class MRenderJSON extends MResult
{

    public function __construct($json = '')
    {
        mtrace('Executing MRenderJSON');
        parent::__construct();
        $this->content = $json;
    }

    public function apply($request, $response)
    {
        $this->nocache($response);
        $response->setHeader('Content-type', 'Content-type: application/json; charset=UTF-8');
        $response->setOut($this->content);
    }

}
