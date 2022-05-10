<?php
namespace Orkester\Persistence\Operand;

use Orkester\Manager;
use Orkester\Persistence\Criteria\PersistentCriteria;

class OperandObject extends PersistentOperand
{

    private PersistentCriteria $criteria;

    public function __construct(object $operand, PersistentCriteria $criteria)
    {
        parent::__construct($operand);
        $this->type = 'object';
        $this->criteria = $criteria;
    }

    public function getSql()
    {
        if (method_exists($this->operand, 'getSql')) {
            return $this->operand->getSql();
        } else { // se não existe o método getSql, acrescenta como parâmetro nomeado
            $name = uniqid('param_');
            $this->criteria->addParameter($this->operand, $name);
            return ':' . $name;
        }
    }

    public function getSqlWhere()
    {
        $platform = Manager::getPersistentManager()->getPersistence()->getPlatform();
        return $platform->convertWhere($this->operand);
    }
}

