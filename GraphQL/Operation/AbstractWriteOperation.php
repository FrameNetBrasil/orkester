<?php

namespace Orkester\GraphQL\Operation;

use Carbon\Carbon;
use GraphQL\Language\AST\ObjectFieldNode;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\MVC\MAuthorizedModel;
use Orkester\Persistence\Map\AttributeMap;

abstract class AbstractWriteOperation extends AbstractMutationOperation
{

    public function isUpdateOperation(array $values, MAuthorizedModel $model): bool
    {
        return in_array($model->getClassMap()->getKeyAttributeName(), $values);
    }

    public function formatValue(string $type, mixed $value, mixed $format)
    {
        return match ($type) {
            'datetime', 'time', 'date', 'timestamp' => Carbon::createFromFormat($format, $value),
            default => $value
        };
    }

    /**
     * @throws EGraphQLForbiddenException|EGraphQLNotFoundException
     */
    public function createEntityArray(array $values, MAuthorizedModel $model, bool $isUpdate): ?array
    {
        $classMap = $model->getClassMap();
        $entity = [];
        $authorize = $isUpdate ?
            fn($attr) => $model->canUpdateAttribute($attr) :
            fn() => true;
        /** @var ObjectFieldNode $fieldNode */
        foreach ($values as $name => $value) {
            if ($attributeMap = $classMap->getAttributeMap($name)) {
                if (is_array($value)) {
                    if (!array_key_exists('value', $value)) {
                        throw new EGraphQLException([$name => 'value_missing']);
                    }
                    ['value' => $provided, 'format' => $format] = $value;
                    if (!empty($format)) {
                        $value = $this->formatValue($attributeMap->getType(), $provided, $format);
                    } else {
                        $value = $provided;
                    }
                }
                $key = $name;
            } else if ($associationMap = $classMap->getAssociationMap($name)) {
                $key = $associationMap->getFromKey();
            } else {
                throw new EGraphQLNotFoundException($name, 'attribute');
            }
            if ($authorize($key)) {
                $entity[$key] = $value;
            } else {
                throw new EGraphQLForbiddenException($name, 'association_write');
            }
        }
        return $entity;
    }
}
