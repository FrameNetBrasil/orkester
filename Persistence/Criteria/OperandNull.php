<?php
namespace Orkester\Persistence\Criteria;

class OperandNull extends PersistentOperand
{

    public function __construct($operand)
    {
        parent::__construct($operand);
        $this->type = 'null';
    }

}

