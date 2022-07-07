<?php

namespace Orkester\Persistence\Map;


use Closure;
use Orkester\Persistence\Enum\Type;
use Orkester\Persistence\Enum\Key;

class AttributeMap
{
    public ClassMap $classMap;
    public string $name;
    public string $columnName;
    public string $alias;
    public mixed $default;
    public bool $nullable = false;
    public bool $virtual = false;
    public string $reference = '';
    public string $idGenerator = '';
    public Type $type = Type::STRING;
    public Key $keyType = Key::NONE;
    public string|Closure|null $validator;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->columnName = $name;
    }

}
