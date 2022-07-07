<?php

namespace Orkester\GraphQL\Selection;

use GraphQL\Language\AST\FieldNode;
use Orkester\GraphQL\Context;
use Orkester\GraphQL\Formatter\BooleanFormatter;
use Orkester\GraphQL\Formatter\DateTimeFormatter;
use Orkester\GraphQL\Formatter\IFormatter;
use Orkester\GraphQL\Parser\Parser;
use Orkester\Authorization\MAuthorizedModel;
use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Model;

class FieldSelection implements \JsonSerializable
{

    public array $formatters;

    public function __construct(
        protected string        $field,
        protected ?string       $alias = null,
        protected ?AttributeMap $attributeMap = null,
        IFormatter              ...$formatters
    )
    {
        $this->formatters = $formatters ?? [];
    }

    public function hasFormatters(): bool
    {
        return count($this->formatters) > 0;
    }

    public function format(mixed $value)
    {
        $v = $value;
        foreach ($this->formatters as $formatter) {
            $v = $formatter->formatOutgoing($value);
        }
        return $v;
    }

    public static function fromNode(FieldNode $node, Model|string $model, Context $context): ?FieldSelection
    {
        $arguments = Parser::toAssociativeArray($node->arguments, null);
        $skipExistCheck = false;
        if ($fieldNode = $arguments['expr'] ?? false) {
            $skipExistCheck = true;
        } else {
            $fieldNode = $arguments['field'] ?? null;
        }
        if ($fieldNode) {
            $field = $context->getNodeValue($fieldNode)(null);
            $alias = $node->alias?->value ?? $node->name->value;
        } else {
            $field = $node->name->value;
            $alias = $node->alias?->value ?? null;
        }
        $attributeMap = $model::getClassMap()->getAttributeMapChain($field);
        if (!$skipExistCheck && !$attributeMap) {
            return null;
        }
        if ($format = $arguments['datetime'] ?? false) {
            $formatters[] = new DateTimeFormatter($context->getNodeValue($format)(null));
        }
        if ($bool = $arguments['boolean'] ?? false) {
            if ($context->getNodeValue($bool)(null)) {
                $formatters[] = new BooleanFormatter();
            }
        }
        return new FieldSelection($field, $alias, $attributeMap, ...$formatters ?? []);
    }

    public function jsonSerialize(): array
    {
        return [
            "field" => $this->field,
            "alias" => $this->alias,
            "formatters" => $this->formatters
        ];
    }

    public function getName(): string
    {
        return $this->alias ?? $this->field;
    }

    public function getSQL(): string
    {
        return $this->field . ($this->alias ? " as $this->alias" : "");
    }

    public function equals($obj): bool
    {
        return $this->alias ?
            ($this->alias === ($obj->alias ?? $obj->field)) :
            ($this->field === ($obj->alias ?? $obj->field));
    }
}
