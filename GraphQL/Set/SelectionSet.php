<?php

namespace Orkester\GraphQL\Set;

use Orkester\GraphQL\Operation\AssociatedQueryOperation;
use Orkester\GraphQL\Selection\FieldSelection;
use Orkester\Persistence\Criteria\RetrieveCriteria;

class SelectionSet implements \JsonSerializable
{
    protected array $associatedQueries;

    public function __construct(
        protected array          $fields,
        public array             $forcedSelection,
        AssociatedQueryOperation ...$associatedQueries
    )
    {
        $this->associatedQueries = $associatedQueries;
    }

    public function getAssociatedQueries(): array
    {
        return $this->associatedQueries;
    }

    public function apply(RetrieveCriteria $criteria): RetrieveCriteria
    {
        foreach ($this->fields as $field) {
            $columns[] = $field();
        }
        $criteria->setColumns(array_merge($criteria->getColumns(), $columns ?? []));
        return $criteria;
    }

    public function format(array &$rows)
    {
        $anyFormatter = array_find($this->fields, fn(FieldSelection $f) => $f->hasFormatters());
        if ($anyFormatter) {
            foreach ($rows as &$row) {
                /**
                 * @var string $key
                 * @var FieldSelection $operator
                 */
                foreach ($this->fields as $key => $operator) {
                    $row[$key] = $operator->format($row[$key]);
                }
            }
        }
    }

    public function isSelected(string $key): bool
    {
        return array_key_exists($key, $this->fields);
    }

    public function jsonSerialize()
    {
        return [
            "fields" => $this->fields,
            "forced_selection" => $this->forcedSelection,
            "associated_queries" => array_map(fn($q) => $q->jsonSerialize(), $this->associatedQueries)
        ];
    }
}
