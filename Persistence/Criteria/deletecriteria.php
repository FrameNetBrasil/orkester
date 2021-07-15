<?php

namespace Orkester\Persistence\Criteria;

use Orkester\Manager;

class DeleteCriteria extends DMLCriteria
{
    public function execute(?array $parameters = [])
    {
        return Manager::getPersistentManager()->getPersistence()->processCriteriaDelete($this, $parameters);
    }
}
