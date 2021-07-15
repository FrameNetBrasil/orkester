<?php

namespace Orkester\Database;

use Doctrine\DBAL;
use Exception;
use Orkester\Exception\EDBException;
use Orkester\Exception\ERuntimeException;
use Orkester\Manager;
use Orkester\Utils\MArrayHelper;

class MDatabase
{

    /**
     * List of supported drivers and their mappings to the driver classes.
     *
     * @var array
     */
    private static $_driverClass = array(
        'sqlite3' => 'Doctrine\DBAL\Driver\SQLite3\Driver',
    );

    /**
     * @var array
     */
    private static $_platformMap = array(
        'pdo_mysql' => \Orkester\Database\Platforms\PDOMySql\Platform::class
    );

    private $config;       // identifies db configuration in conf.php
    private $params;
    private DBAL\Driver\Connection $connection;   // Doctrine\DBAL\Connection object
    private $status;       // 'open' or 'close'
    /** @var DBAL\Platforms\AbstractPlatform */
    private $platform;     // platform of current driver
    private $transaction;
    private $name;
    private $ormLogger = null;
    private $lastInsertId = 0;

    public function __construct(string $databaseName)
    {
        try {
            $this->name = trim($databaseName);
            $this->config = Manager::getConf("db.{$databaseName}");
            $platform = self::$_platformMap[$this->config['driver']];
            $this->platform = new $platform($this);
            $this->config['platform'] = $this->platform;
            $driver = MArrayHelper::getValue(self::$_driverClass, $this->config['driver']);
            if ($driver != '') {
                $this->config['driverClass'] = $driver;
                unset($this->config['driver']);
            }
            $this->connection = $this->newConnection();
            $this->params = $this->connection->getParams();
            $this->platform->connect();
            $ormLogger = MArrayHelper::getValue($this->config, 'ormLoggerClass');
            if ($ormLogger) {
                $this->ormLogger = new $ormLogger();
            }
            if (MArrayHelper::getValue($this->config, 'enableUserInformation')) {
                $this->setUserInformation();
            }
        } catch (Exception $e) {
            Manager::logError(
                'Erro na conexão com o banco de dados: ' . $e->getMessage()
                . ' \nParams: ' . json_encode($this->params)
                . ' \nConfig: ' . json_encode($this->config)
            );
            throw new ERuntimeException('Fail to connect to database: ' . $databaseName);
        }
    }

    /**
     * Get an instance of a DBAL Connection.
     *
     * @return DBAL\Connection
     * @throws DBAL\DBALException
     */
    public function newConnection()
    {
        $configuration = new DBAL\Configuration();
        $logger = new MSqlLogger($this);
        $configuration->setSQLLogger($logger);

        return DBAL\DriverManager::getConnection($this->config, $configuration);
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function getConfig($key)
    {
        $k = explode('.', $key);
        $conf = $this->config;
        foreach ($k as $token) {
            $conf = $conf[$token];
        }

        return $conf;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getPlatform()
    {
        return $this->platform;
    }

    public function getORMLogger()
    {
        return $this->ormLogger;
    }

    public function getTransaction()
    {
        return $this->connection;
    }

    public function lastInsertId()
    {
        return $this->lastInsertId;
    }

    public function beginTransaction()
    {
        $this->connection->beginTransaction();
        return $this->connection;
    }

    public function getSQL(
        $columns = '',
        $tables = '',
        $where = '',
        $orderBy = '',
        $groupBy = '',
        $having = '',
        $forUpdate = false
    ) {
        $sql = new MSql($columns, $tables, $where, $orderBy, $groupBy, $having, $forUpdate);
        $sql->setDb($this);

        return $sql;
    }

    public function executeBatch(/* array of MSql */
        $sqlArray
    ) {
        if (!is_array($sqlArray)) {
            $sqlArray = array($sqlArray);
        }
        try {
            $this->beginTransaction();
            foreach ($sqlArray as $sql) {
                if (is_array($sql)) {
                    foreach ($sql as $data) {
                        $platForm = $data[0];
                        $platForm->handleTypedAttribute($data[1], $data[2], $data[3]);
                    }
                } else {
                    $this->execute($sql);
                }
            }
            $this->lastInsertId = $this->connection->lastInsertId();
            $this->connection->commit();
        } catch (Exception $e) {
            $this->connection->rollBack();
            throw EDBException::execute($e->getMessage());
        }
    }

    public function executeCommand($command, $parameters = null)
    {
        $MSql = new MSql();
        $MSql->setDb($this);
        $MSql->setCommand($command);
        $this->execute($MSql, $parameters);
    }

    public function execute(MSql $sql, $parameters = null)
    {
        if ($this->connection->isTransactionActive()) {
            try {
                $sql->setParameters($parameters);
                $this->affectedRows = $sql->execute();
            } catch (Exception $e) {
                $code = $sql->stmt->errorCode();
                $info = $sql->stmt->errorInfo();
                mdump($info);
                throw EDBException::execute($info[2], $code);
            }
        } else {
            throw EDBException::transaction('No active transaction.');
        }
    }

    public function count(MQuery $query)
    {
        return $query->count();
    }

    public function getNewId($sequence = 'admin')
    {
        try {
            $value = $this->platform->getNewId($sequence);
        } catch (Exception $e) {
            throw EDBException::execute('DB::getNewId: ' . trim($e->getMessage()));
        }

        return $value;
    }

    public function prepare(MSql $sql)
    {
        $sql->prepare();
    }

    public function query(MSql $sql)
    {
        try {
            $query = $this->getQuery($sql);

            return $query->fetchAll();
        } catch (Exception $e) {
            throw EDBException::query($e->getMessage());
        }
    }

    public function executeQuery($command, $parameters = null, $page = null, $rows = null)
    {
        try {
            $query = new MQuery();
            $query->setDb($this);
            $MSql = new MSql();
            $MSql->setCommand($command);
            if ($parameters) {
                $MSql->setParameters($parameters);
            }
            if ($page) {
                $MSql->setRange($page, $rows);
            }
            $query->setSQL($MSql);
            return $query->fetchAll();
        } catch (Exception $e) {
            throw EDBException::query($e->getMessage());
        }
    }

    /**
     * @param $command
     * @return MQuery
     * @throws EDBException
     */
    public function getQueryCommand($command)
    {
        try {
            $query = new MQuery();
            $query->setDb($this);
            $MSql = new MSql();
            $MSql->setCommand($command);
            $query->setSQL($MSql);

            return $query;
        } catch (Exception $e) {
            throw ERuntimeException::query($e->getMessage());
        }
    }

    public function getQuery(MSql $sql)
    {
        try {
            $query = new MQuery();
            $query->setDb($this);
            $query->setSQL($sql);
            return $query;
        } catch (Exception $e) {
            throw ERuntimeException::query($e->getMessage());
        }
    }

    public function getPreparedQuery($command)
    {
        try {
            $query = new MQuery();
            $query->setDb($this);
            $MSql = new MSql();
            $MSql->setDb($this);
            $MSql->setCommand($command);
            $MSql->prepare();
            $query->setSQL($MSql);
            return $query;
        } catch (Exception $e) {
            throw new ERuntimeException($e->getMessage());
        }
    }

    public function getTable($tableName)
    {
        try {
            $sql = new MSql("*", $tableName);
            $query = $this->getQuery($sql);

            return $query;
        } catch (Exception $e) {
            throw ERuntimeException::query($e->getMessage());
        }
    }

    private function setUserInformation()
    {
        $login = Manager::getLogin();

        if (!$login) {
            $userId = 1;
        } else {
            $userId = $login->getIdUser();
        }

        $userIP = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
        $this->platform->setUserInformation($userId, $userIP, null, null);
    }

    public function executeProcedure($sql, $aParams = array(), $aResult = array())
    {
        /* TODO */
    }

    public function ignoreAccentuation($ignore = true)
    {
        $this->platform->ignoreAccentuation($ignore);
    }
}
