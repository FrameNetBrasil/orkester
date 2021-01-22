<?php
namespace Orkester\Persistence\Criteria;

class OperandObject extends PersistentOperand
{

    private $criteria;

    public function __construct($operand, $criteria)
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
        $platform = $this->criteria->getClassMap()->getPlatform();
        return $platform->convertWhere($this->operand);
    }
}

