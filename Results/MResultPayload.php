<?php
declare(strict_types=1);

namespace Orkester\Results;

use JsonSerializable;
use Orkester\MVC\MError;

class MResultPayload implements JsonSerializable
{
    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var array|object|null
     */
    private $data;

    /**
     * @var MError|null
     */
    private $error;

    /**
     * @param int                   $statusCode
     * @param array|object|null     $data
     * @param MError|null      $error
     */
    public function __construct(
        int $statusCode = 200,
        $data = null,
        ?MError $error = null
    ) {
        $this->statusCode = $statusCode;
        $this->data = $data;
        $this->error = $error;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array|null|object
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return MError|null
     */
    public function getError(): ?MError
    {
        return $this->error;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        $payload = [
            'statusCode' => $this->statusCode,
        ];

        if ($this->data !== null) {
            $payload['data'] = $this->data;
        } elseif ($this->error !== null) {
            $payload['error'] = $this->error;
        }

        return $payload;
    }
}
