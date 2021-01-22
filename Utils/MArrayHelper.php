<?php
namespace Orkester\Utils;

class MArrayHelper
{
    /**
     * Recupera um determinado valor no array fazendo as verificações para evitar mensagens de erro.
     *
     * @param array $array Array onde determinado valor, será buscado.
     * @param $key integer ou string Chave do item a ser recuperado.
     * @return object Valor recuperado ou null caso a chave não exista.
     */
    public static function getValue(array $array, string $key)
    {
        if ((!isset($array)) || (!is_array($array))) {
            return null;
        }
        if (array_key_exists($key, $array)) {
            return $array[$key];
        } else {
            return null;
        }
    }
}