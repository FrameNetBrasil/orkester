<?php

namespace Orkester\GraphQL;

use Orkester\Persistence\Criteria\Criteria;

class Result
{
    protected array $results = [];
    protected array $criterias = [];

    public function __construct(protected Configuration $configuration)
    {

    }

    public function addResult(string $name, ?string $alias, mixed $result)
    {
        $result = $this->configuration->isSingular($name) ?
            (array_key_exists(0, $result) ? $result[0] : null) :
            $result;

        $this->results[$alias ?? $name] = $result;
    }

    public function addCriteria(string $name, Criteria $criteria)
    {
        $this->criterias[$name] = $criteria;
    }

    public function getCriteria(string $name): ?Criteria
    {
        return $this->criterias[$name] ?? null;
    }

    public function getResult(string $name): mixed
    {
        return $this->results[$name] ?? null;
    }

    /**
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }
}
