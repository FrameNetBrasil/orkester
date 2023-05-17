<?php

namespace Orkester\Persistence\Criteria;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Monolog\Logger;

class CriteriaBuilder extends Builder
{

    public function __construct(
        protected Logger    $logger,
        ConnectionInterface $connection,
        Grammar             $grammar = null,
        Processor           $processor = null
    )
    {
        parent::__construct($connection, $grammar, $processor);
    }

    public function upsert(array $values, $uniqueBy, $update = null, array $returning = null)
    {
        if (empty($values)) {
            return 0;
        } elseif ($update === []) {
            return (int)$this->insert($values);
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        if (is_null($update)) {
            $update = array_keys(reset($values));
        }

        $this->applyBeforeQueryCallbacks();

        $bindings = $this->cleanBindings(array_merge(
            Arr::flatten($values, 1),
            collect($update)->reject(function ($value, $key) {
                return is_int($key);
            })->all()
        ));
        $sql = $this->grammar->compileUpsert($this, $values, (array)$uniqueBy, $update);
        $sql = $sql . $this->getReturningSql($returning);
        $this->logSql($sql, $bindings);
        /**
         * @var array $rows
         */
        $rows = $this->connection->statement($sql, $bindings);
        return collect(Arr::map($rows, fn($row) => Arr::only($row, $returning)));
    }

    public function insert(array $values, array $returning = null)
    {
        // Since every insert gets treated like a batch insert, we will make sure the
        // bindings are structured in a way that is convenient when building these
        // inserts statements by verifying these elements are actually an array.
        if (empty($values)) {
            return true;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        // Here, we will sort the insert keys for every record so that each insert is
        // in the same order for the record. We need to make sure this is the case
        // so there are not any errors or problems when inserting these records.
        else {
            foreach ($values as $key => $value) {
                ksort($value);

                $values[$key] = $value;
            }
        }

        $this->applyBeforeQueryCallbacks();

        // Finally, we will run this query against the database connection and return
        // the results. We will need to also flatten these bindings before running
        // the query so they are all in one huge, flattened array for execution.
        $sql = $this->grammar->compileInsert($this, $values);
        $sql .= $this->getReturningSql($returning);
        $this->logSql($sql, $values);
        /** @var array $rows */
        $rows = $this->connection->insert(
            $sql,
            $this->cleanBindings(Arr::flatten($values, 1))
        );
        return collect(Arr::map($rows, fn($row) => Arr::only($row, $returning)));
    }

    protected function getReturningSql(?array $returning): string
    {
        if ($returning) {
            $return = Arr::map($returning, fn($r) => $this->grammar->wrap($r));
            $sql = " returning " . implode(',', $return);
        }
        return $sql ?? "";
    }

    public function logSql(string $query, $bindings)
    {
        //$bindings = Arr::flatten($values, 1);
        $values = Arr::map(Arr::wrap($bindings), fn($b) => match (true) {
            is_string($b) => "'$b'",
            is_null($b) => 'NULL',
            default => $b
        });
        $sql = Str::replaceArray('?', $values, $query);
//        foreach ($bindings as $binding) {
//            if (is_null($binding)) continue;
//            //$query = Str::replaceFirst('?', (is_numeric($binding) ? $binding : sprintf('\'%s\'', $binding)), $query);
//        }
        $this->logger->info($sql);
    }

    public function update(array $values, array $returning = null): \Illuminate\Support\Collection
    {
        $this->applyBeforeQueryCallbacks();

        $sql = $this->grammar->compileUpdate($this, $values);
        $sql .= $this->getReturningSql($returning);
        $bindings = $this->cleanBindings(
            $this->grammar->prepareBindingsForUpdate($this->bindings, $values)
        );
        /**
         * @var array $rows
         */
        $rows = $this->connection->statement($sql, $bindings);
        $this->logSql($sql, $bindings);
        if (!$returning) return Collection::empty();
        return collect(Arr::map($rows, fn($row) => Arr::only($row, $returning)));
    }

    public function delete($id = null, array $returning = null)
    {
        // If an ID is passed to the method, we will set the where clause to check the
        // ID to let developers to simply and quickly remove a single row from this
        // database without manually specifying the "where" clauses on the query.
        if (!is_null($id)) {
            $this->where($this->from . '.id', '=', $id);
        }

        $this->applyBeforeQueryCallbacks();

        $sql = $this->grammar->compileDelete($this);
        $sql .= $this->getReturningSql($returning);
        $rows = $this->connection->statement(
            $sql,
            $this->cleanBindings($this->grammar->prepareBindingsForDelete($this->bindings))
        );
        $this->logSql($sql, $this->bindings);

        return collect(Arr::map($rows, fn($row) => Arr::only($row, $returning)));
    }
}
