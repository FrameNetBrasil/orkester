<?php

namespace Orkester\Persistence\Grammar;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Monolog\Logger;
use Orkester\Manager;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Criteria\Operand;
use PHPSQLParser\PHPSQLParser;

trait OrkesterGrammarTrait
{
    protected bool $appendTablePrefix = false;
    public string $context = '';
    private bool $isLogging;
    public Logger $logger;

    /**
     * The components that make up a select clause.
     *
     * @var string[]
     */
    protected array $selectComponentsOrkester = [
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

    protected function parseNode(Criteria $criteria, array $node, string $raw = '', bool $ignoreAlias = false): string
    {
        if (!isset($node['expr_type']) || $node['expr_type'] == 'colref') {
            $op = new Operand($criteria, $node['base_expr'] ?? $raw, ($node['alias'] ?? false) ? $node['alias']['name'] : '');
            $resolved = $op->resolveOperand();
            if ($resolved instanceof Expression) {
                $parser = new PHPSQLParser();
                $parsed = $parser->parse("select " . $resolved->getValue());
                return $this->parseNode($criteria, $parsed['SELECT'][0], $resolved->getValue(), $ignoreAlias);
            }
            if ($resolved == '*') {
                $result = $resolved;
            } else {
                $parts = explode('.', $resolved);
                $column = count($parts) > 1 ? $parts[1] : $parts[0];
                $column = $column == '*' ? '*' : "`$column`";
                $result = count($parts) > 1 && $this->appendTablePrefix ?
                    "`$parts[0]`.$column" :
                    "$column";
            }
            $defaultAlias = $op->alias;
            if ($this->context == 'select' && !empty($operand->alias)) {
                $criteria->fieldAlias[$operand->alias] = $resolved;
            }
        } else if ($node['expr_type'] == 'expression') {
            $args = array_map(
                fn($sub) => $this->parseNode($criteria, $sub, ignoreAlias: true),
                $node['sub_tree']
            );
            $result = implode(' ', $args);
        } else if ($node['expr_type'] == 'function' || $node['expr_type'] == 'aggregate_function') {
            $args = array_map(
                fn($sub) => $this->parseNode($criteria, $sub, ignoreAlias: true),
                $node['sub_tree']
            );
            $argList = implode(',', $args);
            $result = "{$node['base_expr']}($argList)";
        } else {
            $result = $node['base_expr'] ?? $raw;
        }
        $alias = false;
        if (!$ignoreAlias && $this->context == 'columns') {
            $alias = $node['alias'] ?? false ?
                $node['alias']['name'] : $defaultAlias ?? false;
        }
        return $result . ($alias ? " as `$alias`" : '');
    }

    public function wrap($value, $prefixAlias = false): string
    {
        if ($prefixAlias) return parent::wrap($value, $prefixAlias);
        if (($this->context == 'from') || ($this->context == 'joins')) {
            return parent::wrap($value, $prefixAlias);
        } else {
            $parser = new PHPSQLParser();
            $parsed = $parser->parse("select " . $value);
            return $this->parseNode($this->criteria, $parsed['SELECT'][0], $value, $this->context != 'columns' && $this->context != 'from');
        }
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
                $this->context = $component;
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

    public function compileWheres(Builder $query): string
    {
        $original = $this->criteria;
        if ($query instanceof Criteria) {
            $this->criteria = $query;
        }
        $result = parent::compileWheres($query);
        $this->criteria = $original;
        return $result;
    }

    /**
     * Compile a where condition with a sub-select.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereSub(Builder $query, $where)
    {
        $select = $this->compileSelect($where['query']);
        $this->context = 'wheres';
        return $this->wrap($where['column']).' '.$where['operator']." ($select)";
    }


    public function compileHavings(Builder $query): string
    {
        $original = $this->criteria;
        if ($query instanceof Criteria) {
            $this->criteria = $query;
        }
        $result = parent::compileHavings($query);
        $this->criteria = $original;
        return $result;
    }

    public function logSql(string $query, $values): string
    {
        if (!$this->isLogging) return $query;
        $sql = $query;
        $bindings = Arr::flatten($values, 1);
        foreach ($bindings as $binding) {
            $sql = Str::replaceFirst('?', (is_numeric($binding) ? $binding : sprintf('\'%s\'', $binding)), $sql);
        }
        $this->logger->info($sql);
        return $query;
    }

    public function compileSelect(Builder $query): string
    {
        $this->appendTablePrefix = true;
        return $this->logSql(parent::compileSelect($query), $this->criteria->getBindings());
    }

    public function compileUpdate(Builder $query, array $values): string
    {
        $this->appendTablePrefix = false;
        return $this->logSql(parent::compileUpdate($query, $values), $values);
    }

    public function compileDelete(Builder $query): string
    {
        $this->appendTablePrefix = false;
        return $this->logSql(parent::compileDelete($query), $this->criteria->getBindings());
    }

    public function compileUpsert(Builder $query, array $values, array $uniqueBy, array $update): string
    {
        $this->appendTablePrefix = false;
        $wasLogging = $this->isLogging;
        $this->isLogging = false;
        $sql = parent::compileUpsert($query, $values, $uniqueBy, $update);
        $this->isLogging = $wasLogging;
        return $this->logSql($sql, $values);
    }

    public function compileInsert(Builder $query, array $values): string
    {
        $this->appendTablePrefix = false;
        return $this->logSql(parent::compileInsert($query, $values), $values);
    }
}
