<?php

class MAuthDb extends MAuth
{
    var $errors;

    public function authenticate($userId, $pass)
    {
        Manager::logMessage("[LOGIN] Authenticating $userId");
        $login = NULL;

        try {
            $user = Manager::getBusinessMAD('user');
            $user->getByLoginPass($userId, $pass);

            if ($user->login) {
                $login = new MLogin($user);
                if (Manager::getConf("options.dbsession")) {
                    $session = Manager::getBusinessMAD('session');
                    $session->lastAccess($login);
                    $session->registerIn($login);
                }

                $this->setLogin($login);
                Manager::logMessage("[LOGIN] Authenticated $userId");
                return true;
            } else {
                Manager::logMessage("[LOGIN] $userId NOT Authenticated");
            }
        } catch (Exception $e) {
            Manager::logMessage("[LOGIN] $userId NOT Authenticated - " . $e->getMessage());
            $this->errors = $e->getMessage();
        }

        return false;
    }
}
