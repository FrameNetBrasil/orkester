<?php
declare(strict_types=1);

namespace Orkester\Handlers;

use Orkester\Controllers\MError;
use Exception;
use Orkester\Results\MResultObject;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpNotImplementedException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;
use Throwable;

class HttpErrorHandler extends SlimErrorHandler
{
    /**
     * @inheritdoc
     */
    protected function respond(): Response
    {
        $exception = $this->exception;
        $file = $exception->getFile();
        $line = $exception->getLine();
        $message = $exception->getMessage();
        mfatal("$file ($line):  $message");
        $statusCode = 500;
        $error = new MError(
            MError::SERVER_ERROR,
            'An internal error has occurred while processing your request.',
            '',''
//            $file,
//            (string)$line,
        );

        if ($exception instanceof HttpException) {
            $statusCode = $exception->getCode();
            $error->setDescription($message);

            if ($exception instanceof HttpNotFoundException) {
                $error->setType(MError::RESOURCE_NOT_FOUND);
            } elseif ($exception instanceof HttpMethodNotAllowedException) {
                $error->setType(MError::NOT_ALLOWED);
            } elseif ($exception instanceof HttpUnauthorizedException) {
                $error->setType(MError::UNAUTHENTICATED);
            } elseif ($exception instanceof HttpForbiddenException) {
                $error->setType(MError::INSUFFICIENT_PRIVILEGES);
            } elseif ($exception instanceof HttpBadRequestException) {
                $error->setType(MError::BAD_REQUEST);
            } elseif ($exception instanceof HttpNotImplementedException) {
                $error->setType(MError::NOT_IMPLEMENTED);
            }
        }

        if (
            !($exception instanceof HttpException)
            && ($exception instanceof Exception || $exception instanceof Throwable)
            && $this->displayErrorDetails
        ) {
            $error->setDescription($exception->getMessage());
        }

        $result = new MResultObject((object)[
            'data' => null,
            'errors' => [$message],
            'extensions' => [
                'code' => $statusCode,
                'exception' => $exception->getMessage()
            ]
        ]);
        $response = $this->responseFactory->createResponse($statusCode);

        return $result->apply($this->request, $response);
    }
}
