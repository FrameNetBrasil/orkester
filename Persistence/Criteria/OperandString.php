<?php
namespace Orkester\Persistence\Criteria;

use Orkester\Persistence\Map\AttributeMap;
use Orkester\Persistence\Map\ClassMap;

class OperandString extends PersistentOperand
{

    private $criteria;

    public function __construct($operand, $criteria)
    {
        parent::__construct($operand);
        $this->criteria = $criteria;
        $this->type = 'string';
    }

    public function getSql()
    {
        $value = $this->operand;
        $sql = '';
        $tokens = preg_split('/([\s()=]+)/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (count($tokens)) {
            foreach ($tokens as $token) {
                $tk = $token;
                $am = $this->criteria->getAttributeMap($tk);
                if ($am instanceof AttributeMap) {
                    $o = new OperandAttributeMap($am, $tk);
                    $newToken = $o->getSql();
                } else {
                    $tk = $token;
                    if (strrpos($tk, '\\') === false) {
                        $tk = $this->criteria->getClassMap()->getNamespace() . '\\' . $tk;
                    }
                    $cm = $this->criteria->getClassMap($tk);
                    if ($cm instanceof ClassMap) {
                        $newToken = $cm->getTableName();
                    } else {
                        $newToken = $token;
                    }
                }
                $sql .= $newToken;
            }
        } else {
            $sql = $value;
        }
        return $sql;
    }

    public function getSqlWhere()
    {
        $value = $this->operand;
        $sql = '';
        $tokens = preg_split('/([\s()=]+)/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (count($tokens)) {
            foreach ($tokens as $token) {
                $tk = $token;
                if ((preg_match("/'(.*)/", $tk) == 0) && (preg_match("/(.*)'/", $tk) == 0)) {
                    $am = $this->criteria->getAttributeMap($tk);
                    if ($am instanceof AttributeMap) {
                        $o = new OperandAttributeMap($am, $tk);
                        $token = $o->getSqlWhere();
                    }
                }
                $sql .= $token;
            }
        } else {
            $sql = $value;
        }
        return $sql;
    }

    public function getSqlGroup()
    {
        $value = $this->operand;
        $sql = '';
        $tokens = preg_split('/([\s()=]+)/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (count($tokens)) {
            foreach ($tokens as $token) {
                $tk = $token;
                $am = $this->criteria->getAttributeMap($tk);
                if ($am instanceof AttributeMap) {
                    $o = new OperandAttributeMap($am, $tk);
                    $token = $o->getSqlGroup();
                }
                $sql .= $token;
            }
        } else {
            $sql = $value;
        }
        return $sql;
    }

    public function getSqlOrder()
    {
        $value = $this->operand;
        $sql = '';
        $tokens = preg_split('/([\s()=]+)/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (count($tokens)) {
            foreach ($tokens as $token) {
                $tk = $token;
                $am = $this->criteria->getAttributeMap($tk);
                if ($am instanceof AttributeMap) {
                    $o = new OperandAttributeMap($am, $tk);
                    $token = $o->getSqlOrder();
                }
                $sql .= $token;
            }
        } else {
            $sql = $value;
        }
        return $sql;
    }

}

