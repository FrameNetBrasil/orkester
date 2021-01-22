<?php

namespace Orkester\Database;

use Doctrine\DBAL\Driver\Statement;
use Orkester\Manager;
use Orkester\Types\MRange;

class MQuery
{
    public array $result = [];
    public int $row = -1;
    public int $rowCount = 0;
    public int $columnCount = 0;
    public bool $eof = true;
    public bool $bof = true;
    public bool $empty = true;
    public array $metadata = [];
    public ?MDatabase $db = null;
    public ?MSql $msql = null;
    public string $sqlCommand;
    public Statement $statement;
    public bool $fetched = false;
    public int $fetchStyle;
    private $linguistic = false;

    /**
     * MQuery constructor.
     */
    public function __construct()
    {
        $this->fetched = false;
        $this->fetchStyle = Manager::getOptions('fetchStyle') ?: FETCH_NUM;
    }

    public function ignoreAccentuation(): void
    {
        $this->linguistic = true;
    }

    public function count(): int
    {
        $this->enableLinguisticSearch(true);
        return $this->db->getPlatform()->fetchCount($this);
    }

    public function fetchAll(?int $fetchStyle = null): array
    {
        $this->fetchStyle ??= $fetchStyle;
        if (!isset($this->msql->stmt)) {
            $this->msql->select();
        }
        $this->enableLinguisticSearch(true);
        $this->msql->stmt->execute();
        $this->enableLinguisticSearch(false);
        $this->result = $this->db->getPlatform()->fetchAll($this);
        $this->fetched = true;
        $this->rowCount = count($this->result);
        $this->_setMetadata();
        if ($this->rowCount > 0) {
            $this->row = 0;
            $this->empty = $this->eof = $this->bof = false;
        } else {
            $this->result = [];
            $this->row = -1;
            $this->empty= $this->eof = $this->bof = true;
        }
        $this->processErrors();
        return $this->result;
    }

    public function fetchAllObject(?int $fetchStyle = null): array
    {
        $result = $this->fetchAll();
        $this->result = [];
        foreach($result as $row) {
            $this->result[] = (object)$row;
        }
        return $this->result;
    }

    private function enableLinguisticSearch($value)
    {
        /**
         * Só posso desabilitar linguistic por alguém que o habilitou previamente. Isso evita que a chamada a "count"
         * desabilite o recurso no banco.
         */
        if ($this->linguistic) {
            $this->db->ignoreAccentuation($value);
        }
    }

    private function processErrors()
    {
        $error = $this->msql->stmt->errorCode();
        if ($error && ($error != '00000')) {
            throw new Exception($this->msql->stmt->errorInfo());
        }
    }

    public function fetchObject(): object|bool
    {
        if (!isset($this->msql->stmt)) {
            $this->msql->select();
        }
        $this->msql->stmt->execute();
        return $this->db->getPlatform()->fetchObject($this);
    }

    public function setDb(MDatabase $db): void
    {
        $this->db = $db;
    }

    public function setSQL(MSQL $msql): MQuery
    {
        $this->msql = $msql;
        if (!$this->msql->db) {
            $this->msql->db = $this->db;
        }
        return $this;
    }

    public function getCommand(): string
    {
        if (is_null($this->msql->stmt)) {
            $this->msql->select();
            $this->sqlCommand = $this->msql->getCommand();
        }
        return $this->sqlCommand;
    }

    public function setCommand(string $sqlCommand): MQuery
    {
        $this->msql->setCommand($sqlCommand);
        return $this;
    }

    public function setRange(int|MRange $page, ?int $rows = null): MQuery
    {
        if (is_null($rows)) {
            $this->msql->setRange($page);
        } else {
            $this->msql->setRange(new MRange($page, $rows));
        }
        $this->resetCommand();
        return $this;
    }

    public function setParameters(?array $parameters = null): MQuery
    {
        $this->msql->setParameters($parameters);
        return $this->resetCommand();
    }

    public function prepare(): MQuery
    {
        $this->msql->prepare();
        return $this;
    }

    public function bind(?array $parameters = null): MQuery
    {
        if (is_array($parameters)) {
            $this->msql->setParameters($parameters);
        }
        $this->msql->bind();
        $this->fetched = false;
        return $this;
    }

    private function resetCommand(): MQuery
    {
        $this->msql->select();
        $this->sqlCommand = $this->msql->getCommand();
        $this->fetched = false;
        return $this;
    }

    private function _setMetadata(): void
    {
        $platform = $this->db->getPlatform();
        $this->metadata = $platform->getMetadata($this->msql->stmt);
        $this->columnCount = $this->metadata['columnCount'];
    }

    public function getColumnName(int $colNumber): string
    {
        return $this->metadata['fieldname'][$colNumber];
    }

    public function getColumnNames(): array
    {
        return $this->metadata['fieldname'];
    }

    public function getColumnNumber(string $colName):int
    {
        return $this->metadata['fieldpos'][strtoupper($colName)];
    }

    public function getValue(string $colName)
    {
        return $this->result[$this->row][$this->metadata['fieldpos'][strtoupper($colName)]];
    }

    public function fields($fieldName)
    {
        return $this->result[$this->row][$this->metadata['fieldpos'][strtoupper($fieldName)]];
    }

    public function getRowValues(): array
    {
        return $this->result[$this->row];
    }

    public function getCSV(string $separator = ';')
    {
        if (!$this->fetched) {
            $this->result = $this->fetchAll();
        }
        $fileName = Manager::getOptions('tmpPath') . DIRECTORY_SEPARATOR . uniqid('csv_') . '.csv';
        $fp = fopen($fileName, 'w');
        foreach ($this->result as $fields) {
            fputcsv($fp, $fields);
        }
        fclose($fp);
        return $fileName;
    }

    public function getRowObject(): object
    {
        return (object)$this->result[$this->row];
    }

    public function eof(): bool
    {
        return (($this->eof) or ($this->rowCount == 0));
    }

    public function bof(): bool
    {
        return (($this->bof) or ($this->rowCount == 0));
    }

    public function getResult(?int $fetchStyle = null): array
    {
        if (!$this->fetched) {
            $this->result = $this->fetchAll($fetchStyle);
        }
        return $this->result;
    }

    public function getResultObject(): array
    {
        if (!$this->fetched) {
            $this->result = $this->fetchAllObject();
        }
        return $this->result;
    }

    public function first(): array
    {
        if (!$this->fetched) {
            $this->result = $this->fetchAll();
        }
        return $this->empty ? [] : array_shift($this->result);
    }

    public function chunkResult(int|string $key = 0, int|string $value = 1, bool $showKeyValue = false, ?int $fetchStyle = null): array
    {
        $newResult = [];
        $result = $this->getResult($fetchStyle);
        if (!$this->empty) {
            foreach ($result as $row) {
                $sKey = trim($row[$key]);
                $sValue = trim($row[$value]);
                $newResult[$sKey] = ($showKeyValue ? $sKey . " - " : '') . $sValue;
            }
        }
        return $newResult;
    }

    /*
    public function storeResult($key = 0, $value = 1)
    {
        $store = new \stdClass();
        $store->identifier = 'idTable';
        $store->label = 'name';
        $store->items = array();

        foreach ($this->chunkResult($key, $value) as $idTable => $nome) {
            $row = new \stdClass();
            $row->idTable = $idTable;
            $row->name = $nome;
            $store->items[] = $row;
        }
        return $store;
    }
    */

    public function chunkResultMany(int|string $key, array $values, string $type = 'S', string $separator = ''): array
    {
        // type= 'S' : string, otherwise array
        $newResult = [];
        $result = $this->getResult();
        if (!$this->empty) {
            foreach ($result as $row) {
                $sKey = trim($row[$key]);
                if ($type == 'S') {
                    $sValue = '';
                    $n = count($values);
                    for ($i = 0, $j = 1; $i < $n; $i++, $j++) {
                        $sValue .= trim($row[$values[$i]]) . ($j < $n ? $separator : '');
                    }
                } else {
                    $sValue = [];
                    foreach ($values as $v) {
                        $sValue[] = trim($row[$v]);
                    }
                }
                $newResult[$sKey] = $sValue;
            }
        }
        return $newResult;
    }

    public function treeResult($group, $node): array
    {
        $tree = [];
        $result = $this->getResult();
        if (!$this->empty) {
            $node = explode(',', $node);
            $group = explode(',', $group);
            foreach ($result as $row) {
                $aNode = [];
                foreach ($node as $n) {
                    $aNode[] = $row[$n];
                }
                $s = '';
                foreach ($group as $g) {
                    $index = $row[$g];
                    $s .= "[{$index}]";
                }
                eval("\$tree{$s}[] = \$aNode;");
            }
        }
        return $tree;
    }

    /*
    public function asXML($root = 'root', $node = 'node')
    {
        $xml = "<$root>";
        $this->moveFirst();
        while (!$this->eof) {
            $xml .= "<$node>";
            for ($i = 0; $i < $this->columnCount; $i++) {
                $fieldName = strtolower($this->metadata['fieldname'][$i]);
                $xml .= "<$fieldName>" . $this->result[$this->row][$i] . "</$fieldName>";
            }
            $this->moveNext();
            $xml .= "</$node>";
        }
        $xml .= "</$root>";
        return $xml;
    }


    public function asObjectArray($fields = null)
    {
        $this->getResult();
        $fieldNames = is_null($fields) ? null : explode(',', $fields);
        $data = array();
        $this->moveFirst();
        while (!$this->eof) {
            $object = new \stdClass();
            $this->setRowObject($object, $fieldNames);
            $this->moveNext();
            $data[] = $object;
        }
        return $data;
    }
    */

    public function getJSON(): string
    {
        $json = "";
        $result = $this->getResultObject();
        if (!$this->empty) {
            $json = json_encode($result);
        }
        return $json;
    }

    /*
    public function asCSV($showColumnName = false)
    {
        $this->getResult();
        $result = $this->result;
        if ($showColumnName) {
            for ($i = 0; $i < $this->columnCount; $i++) {
                $columns[] = ucfirst($this->metadata['fieldname'][$i]);
            }
            array_unshift($result, $columns);
        }
        $id = uniqid(md5(uniqid("")));  // generate a unique id to avoid name conflicts
        $fileCSV = Manager::getFilesPath($id . '.csv', true);
        $csvDump = new \MCSVDump(Manager::getOptions('csv'));
        $csvDump->save($result, basename($fileCSV));
        return $fileCSV;
    }
    */

    /**
     * Calcula o hash baseado na estrutura da consulta SQL.
     *
     * @return string
     */
    public function hash()
    {
        $sql = $this->getCommand();
        $parameters = json_encode($this->msql->parameters);
        $sha = sha1($sql . $parameters);
        return strtoupper(base_convert($sha, 16, 36));
    }

}