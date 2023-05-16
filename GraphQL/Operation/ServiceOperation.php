<?php

namespace Orkester\GraphQL\Operation;

use Orkester\GraphQL\Result;

class ServiceOperation implements \JsonSerializable
{

    public function __construct(
        protected string  $name,
        protected ?string $alias,
        protected array   $arguments,
        protected mixed   $service,
    )
    {
    }

    public function execute(Result $result)
    {
        $arguments = array_map(fn($arg) => $arg($result), $this->arguments);
        $result->addResult($this->name, $this->alias, ($this->service)(...$arguments));
    }

    public function jsonSerialize(): mixed
    {
        return [
            'name' => $this->name,
            'alias' => $this->alias,
            'arguments' => array_map(fn($arg) => $arg->jsonSerialize(), $this->arguments),
            'service' => $this->service
        ];
    }
}
