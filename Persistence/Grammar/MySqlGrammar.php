<?php

namespace Orkester\Persistence\Grammar;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Monolog\Logger;
use Orkester\Manager;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Criteria\Operand;
use PhpMyAdmin\SqlParser\Parser;

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

    public function wrap($value, $prefixAlias = false)
    {
        if ($prefixAlias) return parent::wrap($value, $prefixAlias);
        $parser = new Parser("select " . $value);
        $exp = $parser->statements[0]->expr[0];
        $operand = new Operand($this->criteria, $exp->expr, $exp->alias ?? '');

        $x = $operand->resolveOperand($this->context);
        if ($this->context == 'select' && !empty($operand->alias)) {
            $originalField = $x;
            $this->criteria->fieldAlias[$operand->alias] = $originalField;
            $alias = " as {$operand->alias}";
            return $x instanceof Expression ?
                new Expression("{$x->getValue()}$alias") :
                "{$x}$alias";
        }
        return parent::wrap($x);


        //                $parser = new Parser("select " . $column);
//                foreach ($parser->statements[0]->expr as $j => $exp) {
//                    $columns[$i] = $this->resolveField('select', $exp->expr, $exp->alias);
//                }

//        return parent::wrap($this->resolve($value), $prefixAlias);
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
        return $cols;
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
