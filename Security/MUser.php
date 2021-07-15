<?php

namespace Orkester\Security;

class MUser
{
    public function __construct(
        public string $login,
        public string $name,
        public int $idUser)
    {
    }

}
