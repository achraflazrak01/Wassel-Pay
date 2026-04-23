<?php

namespace App\Services;

class CryptoService
{
    private const SIGNATURE_ALGO = OPENSSL_ALGO_SHA256;
    
    public function generateKeyPair(): array
    {
        $config = [
            'curve_name' => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];
        
        $privateKey = openssl_pkey_new($config);
        
        if ($privateKey === false) {
            throw new \Exception('Erreur génération clé ECC: ' . openssl_error_string());
        }
        
        openssl_pkey_export($privateKey, $privateKeyPem);
        
        $publicKeyDetails = openssl_pkey_get_details($privateKey);
        $publicKeyPem = $publicKeyDetails['key'];
        
        return [
            'private' => $privateKeyPem,
            'public' => $publicKeyPem,
        ];
    }
    
    public function sign(string $data, string $privateKey): string
    {
        $key = openssl_pkey_get_private($privateKey);
        openssl_sign($data, $signature, $key, self::SIGNATURE_ALGO);
        return base64_encode($signature);
    }
    
    public function verify(string $data, string $signature, string $publicKey): bool
    {
        $key = openssl_pkey_get_public($publicKey);
        
        if ($key === false) {
            throw new \Exception('Clé publique invalide: ' . openssl_error_string());
        }
        
        $result = openssl_verify($data, base64_decode($signature), $key, self::SIGNATURE_ALGO);
        
        if ($result === -1) {
            throw new \Exception('Erreur vérification: ' . openssl_error_string());
        }
        
        return $result === 1;
    }
    
    public static function generateUuid(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }
}
