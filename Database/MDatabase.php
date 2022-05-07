<?php

namespace Orkester\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Orkester\Manager;

class MDatabase
{

    public static function getCapsule(string $databaseName)
    {
        $conf = Manager::getConf("db.{$databaseName}");
        $capsule = new Capsule;

        $capsule->addConnection([
            'driver' => $conf['driver'],
            'host' => $conf['host'],
            'database' => $conf['dbname'] ?? $conf['database'],
            'username' => $conf['user'] ?? $conf['username'],
            'password' => $conf['password'],
            'charset' => $conf['charset'],
            'collation' => $conf['collate'] ?? $conf['collation'],
            'prefix' => $conf['prefix'] ?? '',
        ]);

// Set the event dispatcher used by Eloquent models... (optional)
        $capsule->setEventDispatcher(new Dispatcher(new Container));

// Make this Capsule instance available globally via static methods... (optional)
        $capsule->setAsGlobal();

// Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
        $capsule->bootEloquent();

        return $capsule;

    }


}
