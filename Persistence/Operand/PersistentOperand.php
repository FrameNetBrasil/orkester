<?php
namespace Orkester\Persistence\Operand;

class PersistentOperand
{
    public function __construct (
        public mixed $operand = null,
        public mixed $type = null
    ){}

    public function getSql()
    {
        return '';
    }

    public function getSqlWhere()
    {
        return '';
    }

}

