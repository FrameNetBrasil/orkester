<?php

namespace Orkester\GraphQL\Operation;

use Carbon\Carbon;
use GraphQL\Language\AST\ObjectFieldNode;
use Orkester\Exception\EGraphQLException;
use Orkester\Exception\EGraphQLForbiddenException;
use Orkester\Exception\EGraphQLNotFoundException;
use Orkester\Exception\EGraphQLValidationException;
use Orkester\MVC\MAuthorizedModel;

abstract class AbstractMutationOperation extends AbstractOperation
{
    public function createSelectionResult(MAuthorizedModel $model, array $ids): array
    {
        $operation = new QueryOperation($this->context, $this->root, $model, false);
        $pk = $model->getClassMap()->getKeyAttributeName();
        $criteria = $model->getCriteria()->where($pk, 'IN', $ids);
        return $operation->execute($criteria);
    }

    public static function throwValidationError($errors): array
    {
        $result = [];
        foreach ($errors as $error) {
            foreach ($error as $attribute => $message) {
                if (is_array($message)) {
                    $result[$attribute] ??= [];
                    array_push($result[$attribute], ...$message);
                } else {
                    $result[$attribute][] = $message;
                }
            }
        }
        throw new EGraphQLValidationException($result);
    }

    public static function formatValue(string $type, mixed $value, mixed $format)
    {
        if (is_null($value)) return null;
        return match ($type) {
            'datetime', 'time', 'date', 'timestamp' => Carbon::createFromFormat($format, $value),
            default => $value
        };
    }

    public static function createEntityArray(array $values, MAuthorizedModel $model, bool $isUpdate): ?array
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
                        try {
                            $value = self::formatValue($attributeMap->getType(), $provided, $format);
                        } catch (\Exception $e) {
                            throw new EGraphQLValidationException([$name => ['invalid_value']]);
                        }
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

    abstract function execute(): ?array;
}
