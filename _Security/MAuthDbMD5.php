<?php
namespace Orkester\Security;

use Orkester\Manager;

class MAuthDbMD5 extends MAuth
{
    public function authenticate(string $login, string $challenge, string $response): bool
    {
        Manager::logMessage("[LOGIN] Authenticating {$login} MD5");
        try {
            $userService = Manager::getService(UserService::class);
            $this->user = $userService->getByLogin($login);
            mtrace("Authenticate userID = {$login}");
            if ($this->user->validatePasswordMD5($challenge, $response)) {
                // get MLogin object
                $objLogin = new MLogin($this->user);
                // store MLogin in session
                $session = Manager::getSession();
                $session->setValue('__sessionLogin', $objLogin);
                Manager::logMessage("[LOGIN] Authenticated {$login} MD5");
                return true;
            } else {
                Manager::logMessage("[LOGIN] {$login} NOT Authenticated MD5");
            }
        } catch (EMException $e) {
            Manager::logMessage("[LOGIN] {$login} NOT Authenticated MD5 - " . $e->getMessage());
        }
        return false;
    }

    public function validate($login, $challenge, $response)
    {
        $user = Manager::getModelMAD('user');
        $user = $user->getByLogin($login);
        return $user->validatePasswordMD5($challenge, $response);
    }

}
