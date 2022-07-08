<?php

namespace Orkester\Persistence\Grammar;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Monolog\Logger;
use Orkester\Manager;
use Orkester\Persistence\Criteria\Criteria;
use Orkester\Persistence\Criteria\Operand;

class MySqlGrammar extends \Illuminate\Database\Query\Grammars\MySqlGrammar
{
    public string $context = '';
    private bool $isLogging;
    public Logger $logger;

    public function __construct(public Criteria $criteria)
    {
        $this->isLogging = !(Manager::getConf('logs.sql') === false);
        $this->logger = Manager::getContainer()->get('SqlLogger');
    }

    public function resolve(string $value): array|string|null
    {
        $result = preg_replace_callback("/([\w\.\*]+)( as ([\w]+))?/",
            function ($matches) use ($value) {
                $operand = new Operand($this->criteria, $matches[1], $matches[3] ?? '');
                return $operand->resolveOperand($this->context);
            }, $value, -1, $count);
        return $count == 0 ? $value : $result;
    }

    public function wrap($value, $prefixAlias = false)
    {
        if ($prefixAlias) return parent::wrap($value, $prefixAlias);
        if (preg_match("/^(\'.*\')$/", $value, $matches)) {
            return $matches[1];
        }
        $functionCall = preg_replace_callback("/([\w]+)\((.+)\)/",
            function ($matches) {
                $args = preg_replace_callback("/[\s]?((\'.*\')|([\w\. ]+))[,\s]?/",
                    function ($arguments) {
                        $comma = str_ends_with($arguments[0], ',') ? ',' : '';
                        return $this->wrap($arguments[1]) . $comma;
                    }, $matches[2]
                );
                return "$matches[1]($args)";
            },
            $value, -1, $callCount);
        if ($callCount > 0) {
            return $functionCall;
        }
        return parent::wrap($this->resolve($value), $prefixAlias);
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
