<?php
declare(strict_types=1);

namespace Orkester\Controllers;

use JsonSerializable;

class MError implements JsonSerializable
{
    public const BAD_REQUEST = 'BAD_REQUEST';
    public const INSUFFICIENT_PRIVILEGES = 'INSUFFICIENT_PRIVILEGES';
    public const NOT_ALLOWED = 'NOT_ALLOWED';
    public const NOT_IMPLEMENTED = 'NOT_IMPLEMENTED';
    public const RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    public const SERVER_ERROR = 'SERVER_ERROR';
    public const UNAUTHENTICATED = 'UNAUTHENTICATED';
    public const VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const VERIFICATION_ERROR = 'VERIFICATION_ERROR';
    public const SERVICE_ERROR = 'SERVICE_ERROR';

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $file;

    /**
     * @var string
     */
    private $line;

    /**
     * @param string        $type
     * @param string|null   $description
     */
    public function __construct(string $type, ?string $description, ?string $file, ?string $line)
    {
        $this->type = $type;
        $this->description = $description;
        $this->file = $file;
        $this->line = $line;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return self
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string|null $description
     * @return self
     */
    public function setDescription(?string $description = null): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        $payload = [
            'type' => $this->type,
            'description' => $this->description,
            'file' => $this->file,
            'line' => $this->line,
        ];

        return $payload;
    }
}
