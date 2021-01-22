<?php
namespace Orkester\Persistence\Criteria;

class PersistentOperand
{

    public $operand;
    public $type;

    public function __construct($operand)
    {
        $this->operand = $operand;
        $this->type = '';
    }

    public function getSql()
    {
        return '';
    }

    public function getSqlWhere()
    {
        return $this->getSql();
    }

}

