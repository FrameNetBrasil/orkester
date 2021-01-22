<?php

namespace Orkester\Security;

use Orkester\Manager;
use App\Models\User;

class MAuth
{
    protected User $user;

    public function getUser(): User
    {
        return $this->user;
    }

    public function registerLogin(User $user): void {
        $objLogin = new MLogin($user);
        $this->user = $user;
        $session = Manager::getSession();
        $session->setValue('__sessionLogin', $objLogin);
        Manager::logMessage("[LOGIN] Registering {$user->name}");
    }

    public function checkLogin(): bool
    {
        Manager::logMessage('[LOGIN] Running CheckLogin');

        /*
        // if not checking logins, we are done
        if ((!MUtil::getBooleanValue(Manager::$conf['login']['check']))) {
            Manager::logMessage('[LOGIN] I am not checking login today...');
            return true;
        }
        */
        $login = Manager::getLogin();

        // if we have already a login, assume it is valid and return
        if (!is_null($login)) {
            Manager::logMessage('[LOGIN] Using existing login: ' . $login->getLogin());
            return true;
        }

        // we have a session login?
        $session = Manager::getSession();
        $login = $session->getValue('__sessionLogin');
        if ($login instanceof MLogin) {
            if ($login->getLogin()) {
                Manager::logMessage('[LOGIN] Using session login: ' . $login->getLogin());
                Manager::setLogin($login);
                return true;
            }
        }

        Manager::logMessage('[LOGIN] No Login but Login required!');
        return false;
    }

    /**
     * Must be override by specialized classes
     * @param string $login
     * @param string $challenge
     * @param string $response
     * @return bool
     */
    public function authenticate(string $login, string $challenge, string $response): bool
    {
        return false;
    }

    public function logout($forced = '')
    {
        Manager::getSession()->destroy();
    }

}
