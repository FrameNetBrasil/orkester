<?php


namespace Orkester\MVC;


use MMailer;
use Orkester\Manager;

class MServiceMaestro extends MControllerMaestro
{
    public function getDatabase($name)
    {
        return Manager::getDatabase($name);
    }

    /**
     *
     * @return \PHPMailer
     * @deprecated
     */
    public function getMail()
    {
        return MMailer::getMailer();
    }
}