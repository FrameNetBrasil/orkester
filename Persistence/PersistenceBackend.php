<?php


namespace Orkester\Persistence;


interface PersistenceBackend
{
    public function execute(array $commands);
}