<?php

namespace Orkester\Exception;

class GraphQLNotFoundException extends \Exception implements GraphQLException
{
    public function __construct(protected string $what, protected string $where, int $code = 404)
    {
        parent::__construct("$what not found in $where", $code);
    }

    public function getType(): string
    {
        return "not_found";
    }

    public function getDetails(): array
    {
        return [
            "what" => $this->what,
            "where" => $this->where
        ];
    }
}
