<?php

namespace Orkester\GraphQL\Set;

use Orkester\GraphQL\Operation\AssociatedQueryOperation;
use Orkester\GraphQL\Selection\FieldSelection;
use Orkester\Persistence\Criteria\Criteria;

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

    public function apply(Criteria $criteria): Criteria
    {
        $old = $criteria->columns;
        $new = array_map(fn(FieldSelection $f) => $f->getSQL(), $this->fields);
        $criteria->columns = array_merge($old ?? [], $new ?? []);
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
//        return !in_array($key, $this->forcedSelection) && array_key_exists($key, $this->fields);
        return array_key_exists($key, $this->fields);
    }

    public function fields()
    {
        return array_map(fn(FieldSelection $f) => $f->getSQL(), $this->fields);
    }

    public function jsonSerialize(): mixed
    {
        return [
            "fields" => $this->fields,
            "forced_selection" => $this->forcedSelection,
            "associated_queries" => array_map(fn($q) => $q->jsonSerialize(), $this->associatedQueries)
        ];
    }
}
