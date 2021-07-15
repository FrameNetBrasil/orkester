<?php

namespace Orkester\Persistence\Operand;

class OperandNull extends PersistentOperand
{

    public function __construct(mixed $operand)
    {
        parent::__construct($operand);
        $this->type = 'null';
    }

}

