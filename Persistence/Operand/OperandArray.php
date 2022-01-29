<?php
namespace Orkester\Persistence\Operand;

class OperandArray extends PersistentOperand
{

    public function __construct(array $operand)
    {
        parent::__construct($operand);
        $this->type = 'array';
    }

    public function getSql(): string
    {
        $sql = "(";
        if (is_array($this->operand)) {
            $operand = array_map(fn($x) => (is_string($x) ? "'{$x}'" : $x), $this->operand);
            $sql .= empty($operand) ? "''" : implode(',', $operand);
        } else {
            $sql .= "'{$this->operand}'";
        }
        $sql .= ")";
        return $sql;
    }

    public function getSqlWhere(): string
    {
        return $this->getSql();
    }

}

