<?php

namespace Orkester\Database\Platforms\PDOMySql;

use Carbon\Carbon;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Orkester\Manager;
use Orkester\Types\MRange;

class Platform extends MySqlPlatform {

    public $db;

    public function __construct($db) {
        parent::__construct();
        $this->db = $db;
    }

    public function connect() {
        $charset = $this->db->getConfig('charset');
        if ($charset) {
            $this->db->getConnection()->exec("SET CHARACTER SET '{$charset}'");
        }
        $collate = $this->db->getConfig('collate');
        if ($collate) {
            $this->db->getConnection()->exec("SET collation_connection = '{$collate}'");
        }
    }

    public function getTypedAttributes() {
        return ''; //'lob,blob,clob,text';
    }

    public function getSetOperation($operation) {
        $operation = strtoupper($operation);
        $set = array('UNION' => 'UNION', 'UNION ALL' => 'UNION', 'INTERSECT' => 'INTERSECT', 'MINUS' => 'MINUS');
        return $set[$operation];
    }

    public function getNewId($sequence = 'admin') {
        $this->value = $this->_getNextValue($sequence);
        return $this->value;
    }

    private function _getNextValue($sequence = 'admin') {
        $transaction = $this->db->beginTransaction();
        $table = $this->db->getConfig('sequence.table');
        $name = $this->db->getConfig('sequence.name');
        $field = $this->db->getConfig('sequence.value');
        $sql = new \database\MSQL($field, $table, "({$name} = '$sequence')");
        $sql->setForUpdate(true);
        $result = $this->db->query($sql);
        $value = $result[0][0];
        $nextValue = $value + 1;
        $this->db->execute($sql->update($nextValue), $nextValue);
        $transaction->commit();
        return $value;
    }

    public function lastInsertId() {
        return $this->db->getConnection()->lastInsertId();
    }

    public function getMetaData($stmt) {
        $s = $stmt->getWrappedStatement();
        $metadata['columnCount'] = $count = $s->columnCount();
        for ($i = 0; $i < $count; $i++) {
            $meta = $s->getColumnMeta($i);
            $name = strtoupper($meta['name']);
            $metadata['fieldname'][$i] = $name;
            $metadata['fieldtype'][$name] = $this->_getMetaType($meta['pdo_type']);
            $metadata['fieldlength'][$name] = $meta['len'];
            $metadata['fieldpos'][$name] = $i;
        }
        return $metadata;
    }

    private function _getMetaType($pdo_type) {
        if ($pdo_type == \PDO::PARAM_BOOL) {
            $type = 'B';
        } else if ($pdo_type == \PDO::PARAM_NULL) {
            $type = ' ';
        } else if ($pdo_type == \PDO::PARAM_INT) {
            $type = 'N';
        } else if ($pdo_type == \PDO::PARAM_STR) {
            $type = 'C';
        } else if ($pdo_type == \PDO::PARAM_LOB) {
            $type = 'O';
        } else {
            $type = 'C';
        }
        return $type;
    }

    public function getSQLRange(MRange $range) {
        return ($range->rows ? "LIMIT $range->rows" : '') . ($range->offset ? " OFFSET $range->offset" : '');
    }

    public function fetchAll($query) {
        return $query->msql->stmt->fetchAll($query->fetchStyle);
    }

    public function fetchObject($query) {
        $stmt = $query->msql->stmt->getWrappedStatement();
        return $stmt->fetchObject();
    }

    public function convertToDatabaseValue($value, $type, &$bindingType) {
        if ($type == '') {
            if (is_object($value)) {
                $type = substr(strtolower($value::class), 1);
            }
        }
        if ($type == 'date') {
            return is_object($value) ? $value->format('Y/m/d') : $value;
        } elseif ($type == 'time') {
            return is_object($value) ? $value->format('H:i:s') : $value;
        } elseif ($type == 'timestamp' || $type == 'datetime') {
            return is_object($value) ? $value->format('Y/m/d H:i:s') : $value;
        } elseif (($type == 'decimal') || ($type == 'float')) {
            return str_replace(',', '.', $value);
        } elseif ($type == 'json') {
            return json_encode($value);
        } elseif ($type == 'blob') {
            $value = base64_encode($value->getValue());
            $bindingType = 3; //PDO::PARAM_LOB
            return $value;
        } else {
            return $value;
        }
    }

    public function convertToPHPValue($value, $type) {
        if (empty($value) && $value != '0') {
            return null;
        }
        if ($type == 'date') {
            return Carbon::createFromFormat('Y-m-d', $value);
        } elseif ($type == 'timestamp' || $type == 'datetime') {
            return Carbon::createFromFormat('Y-m-d H:i:s', $value);
        } elseif ($type == 'time') {
            return Carbon::createFromFormat('H:i:s', $value);
        } elseif ($type == 'json') {
            return json_decode($value);
        } elseif ($type == 'blob') {
            if ($value) {
                $value = base64_decode($value);
            }
            $value = \MFile::file($value);
            return $value;
        } else {
            return $value;
        }
    }

    public function convertColumn($value, $type) {
//        if ($type == 'date') {
//            return "DATE_FORMAT(" . $value . ",'" . $this->db->getConfig('formatDate') . "') ";
//        } elseif ($type == 'datetime' || $type == 'timestamp') {
//            return "DATE_FORMAT(" . $value . ",'" . $this->db->getConfig('formatDate') . ' ' . $this->db->getConfig('formatTime') . "') ";
//        } elseif ($type == 'time') {
//            return "DATE_FORMAT(" . $value . ",'" . $this->db->getConfig('formatTime') . "') ";
//        } else {
//            return $value;
//        }
        return $value;
    }

    public function convertWhere($value, ?string $dbalType = '') {
        if ($dbalType == 'date') {
            return "DATE_FORMAT(" . $value . ",'" . $this->db->getConfig('formatDateWhere') . "') ";
        } elseif ($dbalType == 'datetime') {
            return " DATE_FORMAT(" . $value . ",'" . $this->db->getConfig('formatDateWhere') . ' ' . $this->db->getConfig('formatTimeWhere') . "') ";
        } elseif ($dbalType == 'timestamp') {
            return " DATE_FORMAT(" . $value . ",'" . $this->db->getConfig('formatDateWhere') . ' ' . $this->db->getConfig('formatTimeWhere') . "') ";
        } elseif ($dbalType == 'time') {
            return "DATE_FORMAT(" . $value . ",'" . $this->db->getConfig('formatTimeWhere') . "') ";
        } else
            return $value;
        }

    public function handleTypedAttribute($attributeMap, $operation) {
        $method = 'handle' . $attributeMap->getType();
        $this->$method($operation);
    }

    public function setUserInformation($userId, $userIP = null, $module = null, $action = null) {

    }

    private function handleLOB($operation) {

        //mdump('platform::handleLob');
    }

    private function handleBLOB($operation) {

        //mdump('platform::handleBLob');
    }

    private function handleText($operation) {

        //mdump('platform::handleText');
    }

}
