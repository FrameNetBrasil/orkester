<?php

namespace Orkester\Persistence\Grammar;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Monolog\Logger;
use Orkester\Manager;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Criteria\Operand;
use PHPSQLParser\PHPSQLParser;

class MySqlGrammar extends \Illuminate\Database\Query\Grammars\MySqlGrammar
{
    public string $context = '';
    private bool $isLogging;
    public Logger $logger;

    /**
     * The components that make up a select clause.
     *
     * @var string[]
     */
    protected $selectComponentsOrkester = [
        'aggregate',
        'columns',
        'from',
        'wheres',
        'groups',
        'havings',
        'orders',
        'joins',
        'limit',
        'offset',
        'lock',
    ];

    public function __construct(public Criteria $criteria)
    {
        $this->isLogging = !(Manager::getConf('logs.sql') === false);
        $this->logger = Manager::getContainer()->get('SqlLogger');
    }

    /*
    public function resolve(string $value): array|string|null
    {
        $result = preg_replace_callback("/([\w\.\*]+)( as ([\w]+))?/",
            function ($matches) use ($value) {
                $operand = new Operand($this->criteria, $matches[1], $matches[3] ?? '');
                return $operand->resolveOperand($this->context);
            }, $value, -1, $count);
        return $count == 0 ? $value : $result;
    }
    */

    public function parseNode(array $node, string $raw = ''): string
    {
        if (!isset($node['expr_type']) || $node['expr_type'] == 'colref') {
            $op = new Operand($this->criteria, $node['base_expr'] ?? $raw, ($node['alias'] ?? false) ? $node['alias']['name'] : '');
            $resolved = $op->resolveOperand($this->context);
            if ($resolved == '*') {
                $result = $resolved;
            }
            else {
                $parts = explode('.', $resolved);
                $column = count($parts) > 1 ? $parts[1] : $parts[0];
                $column = $column == '*' ? '*' : "`$column`";
                $result = count($parts) > 1 ?
                    "`$parts[0]`.$column" :
                    "$column";
            }
            $defaultAlias = $op->alias;
            if ($this->context == 'select' && !empty($operand->alias)) {
                $this->criteria->fieldAlias[$operand->alias] = $resolved;
            }
        }
        else if ($node['expr_type'] == 'expression') {
            $args = array_map(
                fn($sub) => $this->parseNode($sub),
                $node['sub_tree']
            );
            $result = implode(' ', $args);
        } else if ($node['expr_type'] == 'function' || $node['expr_type'] == 'aggregate_function') {
            $args = array_map(
                fn($sub) => $this->parseNode($sub),
                $node['sub_tree']
            );
            $argList = implode(',', $args);
            $result = "{$node['base_expr']}($argList)";
        } else {
            $result = $node['base_expr'] ?? $raw;
        }
        $alias = false;
        if ($this->context == 'select') {
            $alias = $node['alias'] ?? false ?
                $node['alias']['name'] : $defaultAlias ?? false;
        }
        return $result . ($alias ? " as `$alias`" : '');
    }

    public function wrap($value, $prefixAlias = false)
    {
        if ($prefixAlias) return parent::wrap($value, $prefixAlias);
        $parser = new PHPSQLParser();
        $parsed = $parser->parse("select " . $value);
        return $this->parseNode($parsed['SELECT'][0], $value);
    }

    public function columnize(array $columns): string
    {
        $this->context = 'select';
        $cols = implode(',',
            array_map(
                fn($col) => $this->wrap($col),
                $columns
            ),
        );
        $this->context = '';
        return trim($cols, ',');
    }

    public function logSql(string $query, $values): string
    {
        if (!$this->isLogging) return $query;
        $sql = $query;
        $bindings = Arr::flatten($values, 1);
        foreach ($bindings as $binding) {
            $sql = Str::replaceFirst('?', (is_numeric($binding) ? $binding : sprintf('"%s"', $binding)), $sql);
        }
        $this->logger->info($sql);
        return $query;
    }

    /**
     * Compile the components necessary for a select clause.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @return array
     */
    protected function compileComponents(Builder $query)
    {
        $sqlOrkester = [];

        foreach ($this->selectComponentsOrkester as $component) {
            if (isset($query->$component)) {
                $method = 'compile' . ucfirst($component);

                $sqlOrkester[$component] = $this->$method($query, $query->$component);
            }
        }

        $sql = [];
        foreach ($this->selectComponents as $component) {
            if (isset($sqlOrkester[$component])) {
                $sql[$component] = $sqlOrkester[$component];
            }
        }

        return $sql;
    }


    public function compileSelect(Builder $query): string
    {
        return $this->logSql(parent::compileSelect($query), $this->criteria->getBindings());
    }

    public function compileUpdate(Builder $query, array $values): string
    {
        return $this->logSql(parent::compileUpdate($query, $values), $values);
    }

    public function compileDelete(Builder $query): string
    {
        return $this->logSql(parent::compileDelete($query), $this->criteria->getBindings());
    }

    public function compileUpsert(Builder $query, array $values, array $uniqueBy, array $update): string
    {
        return $this->logSql(parent::compileUpsert($query, $values, $uniqueBy, $update), $values);
    }

    public function compileInsert(Builder $query, array $values): string
    {
        return $this->logSql(parent::compileInsert($query, $values), $values);
    }

}
