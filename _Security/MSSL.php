<?php
namespace Orkester\Security;
/**
 * Classe para agrupar serviços de encriptação e decriptação utilizando SSL.
 * @author Marcello
 */
class MSSL
{
    /**
     * Gera um par de chaves Publica/Privada.
     *
     * @param int $size Tamanho em bits da chave
     * @return array Chaves pública e privada
     */
    public static function generateKeyPair(int $size = 4096): array
    {
        $config = [
            "digest_alg" => "sha512",
            "private_key_bits" => $size,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];

        $privateKey = null;
        $res = openssl_pkey_new($config);
        openssl_pkey_export($res, $privateKey);
        $publicKey = openssl_pkey_get_details($res);
        $publicKey = $publicKey["key"];

        return [
            'public' => $publicKey,
            'private' => $privateKey
        ];
    }

    /**
     * Criptografia assimétrica: usa uma chave privada para descriptografar
     * o conteúdo criptografado com uma chave pública.
     *
     * @param string $data Conteúdo criptografado
     * @param string $privKey Chave privada
     * @param bool $base64Decode Informa de $data deve ser convertido de base64
     * @return type Valor decriptografado ou null em caso de erro.
     */
    public static function decryptPrivate(string $data, string $privateKey, bool $base64Decode = true): string
    {
        $decoded = $base64Decode ? base64_decode($data) : $data;
        $decrypted = null;
        openssl_private_decrypt($decoded, $decrypted, $privateKey);
        return $decrypted;
    }

    /**
     * Criptografia simétrica.
     *
     * @param string $data Conteúdo em texto puro a ser criptografado.
     * @param string $key Chave criptográfica.
     * @param string $method Método utilizado para criptografia. Padrão AES256.
     * @param type $iv Vetor de inicialização.
     * @return binary Dados criptografados
     */
    public static function simmetricEncrypt(string $data, string $key, string $method = 'aes256', string $iv = '0000000000000000'): string
    {
        return base64_encode(openssl_encrypt($data, $method, $key, 0, $iv));
    }

    /**
     * Decriptografia assimétrica.
     *
     * @param binary $encrypted Conteúdo criptografado.
     * @param type $key Chave criptográfica.
     * @param type $method Método utilizado na criptografia.
     * @param type $iv Vetor de inicialização.
     * @return string Dados decriptografados.
     */
    public static function simmetricDecrypt(string $encrypted, string $key, string $method = 'aes256', string $iv = '0000000000000000'): string
    {
        return openssl_decrypt(base64_decode($encrypted), $method, $key, 0, $iv);
    }

    /**
     * Gera uma string aleatória. Útil para senhas ou chaves temporárias para criptografia simétrica.
     * @param int $size Tamanho da string
     * @param string $alphabet Caracteres que serão utilizados para a geração da string
     * @return string
     */
    public static function randomString(int $size, string $alphabet = 'abcdefghijklmopqrstuvxzABCDEFGHIJKLMNOPQRSTUVXZ0123456789_-+=@#!$()'): string
    {
        $string = '';
        for ($i = 0; $i < $size; $i++) {
            $string .= $alphabet[mt_rand(0, strlen($alphabet) - 1)];
        }
        return $string;
    }
}
