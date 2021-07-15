<?php
namespace Orkester\Persistence\Criteria;

use Orkester\Manager;

class UpdateCriteria extends DMLCriteria
{

    public function update(array $set = [])
    {
        $this->columns = [];
        $this->parameters = [];
        foreach($set as $attribute => $value) {
            $this->columns[] = $attribute;
            $this->parameters[] = $value;
        }
        return $this;
    }

    public function execute(?array $parameters = [])
    {
        return Manager::getPersistentManager()->getPersistence()->processCriteriaUpdate($this, $parameters);
    }
}
