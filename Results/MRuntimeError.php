<?php
namespace Orkester\Results;

use Orkester\Manager;
use Orkester\Services\Http\MRequest;
use Orkester\Services\Http\MResponse;
use Orkester\Services\Http\MStatusCode;

/**
 * MRuntimeError.
 * Retorna template preenchido com dados sobre o erro.
 * Objeto JSON = {'id':'error', 'type' : 'page', 'data' : '$html'}
 */
class MRuntimeError extends MResult
{
    private string $message;

    public function __construct(string $message = '')
    {
        parent::__construct();
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function apply(MRequest $request, MResponse $response)
    {
        $response->setStatus(MStatusCode::NOT_FOUND);
        $html = $this->getTemplate('runtime');
        try {
            if ($request->isAjax()) {
                $this->ajax->setId('error');
                $this->ajax->setType('page');
                $this->ajax->setData($html);
                $out = $this->ajax->returnData();
            } else {
                $out = $html;
            }
            $response->setOut($out);
        } catch (\Exception $e) {
            Manager::logError($e->getMessage());
        }
    }
}
