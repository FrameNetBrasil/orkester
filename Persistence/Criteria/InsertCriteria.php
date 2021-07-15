<?php

namespace Orkester\Persistence\Criteria;

use Orkester\Manager;

class InsertCriteria extends DMLCriteria
{

    private array $rows = [];

    public function rows(array $rows): InsertCriteria
    {
        $this->rows = $rows;
        return $this;
    }

    public function getRows()
    {
        return $this->rows;
    }

    public function execute(?array $parameters = [])
    {
        return Manager::getPersistentManager()->getPersistence()->processCriteriaInsert($this, $parameters);
    }
}
