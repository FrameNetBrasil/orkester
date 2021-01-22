<?php

namespace Orkester\Persistence\Criteria;

class OperandStringAI extends OperandString
{
    public function getSqlWhere()
    {
        return $this->getSql();
    }

    public function getSql()
    {
        $sql = parent::getSql();

        return "TRANSLATE( $sql, 'ÁÇÉÍÓÚÀÈÌÒÙÂÊÎÔÛÃÕËÜáçéíóúàèìòùâêîôûãõëü', 'ACEIOUAEIOUAEIOUAOEUaceiouaeiouaeiouaoeu')";

    }
}

