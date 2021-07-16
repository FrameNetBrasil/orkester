<?php
namespace Orkester\Security;

use Orkester\Manager;

class MLogin
{

    private string $login;
    private int $time;
    private string $name;
    private $userData;
    private MUser $user;
    private int $idUser;
    private bool $isAdmin;
    private array $groups;
    private string $lastAccess;

    public function __construct(MUser $user)
    {
        $this->setUser($user);
        $this->time = time();
    }

    public function setUser($user)
    {
        $this->login = $user->login ?? $user->auth0;
        $this->name = $user->name;
        $this->idUser = $user->idUser;
        $this->user = $user;
        //$this->setGroups($user->getArrayGroups());
        //$this->setRights($user->getRights());
    }

    public function getUser(): MUser
    {
        return $this->user;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function getIdUser(): int
    {
        return $this->idUser;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTime(): int
    {
        return $this->time;
    }

    public function getUserData()
    {
        return $this->userData;
    }

    public function setUserData($data)
    {
        $this->userData = $data;
    }

    /*
    public function setRights($rights)
    {
        $this->rights = $rights;
    }

    public function getRights($transaction = '')
    {
        if ($transaction) {
            return array_key_exists($transaction, $this->rights) ? $this->rights[$transaction] : null;
        }
        return $this->rights;
    }
    */

    public function setGroups(array $groups = [])
    {
        $this->groups = $groups;
        $this->isAdmin(in_array('ADMIN', $groups));
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    public function isAdmin($isAdmin = NULL): bool
    {
        if ($isAdmin !== NULL) {
            $this->isAdmin = $isAdmin;
        }
        return $this->isAdmin;
    }

    public function isMemberOf($group): bool
    {
        return in_array($group, $this->groups);
    }

    public function checkAccess($group): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        return array_key_exists($group, $this->groups);
    }

    public function setLastAccess($data)
    {
        $this->lastAccess->tsIn = $data->tsIn;
        $this->lastAccess->tsOut = $data->tsOut;
        $this->lastAccess->remoteAddr = $data->remoteAddr;
    }


}
