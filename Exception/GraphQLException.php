<?php

namespace Orkester\Exception;

interface GraphQLException
{
    public function getType(): string;
    public function getDetails();
    public function getTrace(): array;
    /**
     * Must be compatible with Exception getCode() return type.
     * @return mixed|int the exception code as integer in
     */
    public function getCode();
}
