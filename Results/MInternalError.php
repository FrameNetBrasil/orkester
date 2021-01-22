<?php

namespace Orkester\Results;

use Orkester\Manager;
use Orkester\Services\Http\MRequest;
use Orkester\Services\Http\MResponse;
use Orkester\Services\Http\MStatusCode;

/**
 * MInternalError.
 * Retorna template preenchido com dados sobre o erro.
 * Objeto JSON = {'id':'error', 'type' : 'page', 'data' : '$html'}
 */
class MInternalError extends MResult
{

    protected \Exception $exception;
    private string $message;

    public function __construct(\Exception $exception)
    {
        parent::__construct();
        $this->exception = $exception;
        $this->message = $this->exception->getMessage();
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function apply(MRequest $request, MResponse $response)
    {
        $response->setStatus(MStatusCode::INTERNAL_ERROR);
        mtrace('InternalError: ' . $this->message);
        $html = $this->getTemplate('500');
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
