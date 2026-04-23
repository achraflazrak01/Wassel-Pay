<?php

namespace App\Services;

class CryptoService
{
    private const CURVE = "prime256v1";
    private const CIPHER = "aes-256-gcm";
    private const SIGNATURE_ALGO = "sha256";
    
    public function generateKeyPair(): array
    {
        $config = [
            "curve_name" => self::CURVE,
            "private_key_type" => OPENSSL_KEYTYPE_EC,
        ];
        
        $privateKey = openssl_pkey_new($config);
        
        if ($privateKey === false) {
            throw new \Exception("Erreur génération clé ECC: " . openssl_error_string());
        }
        
        openssl_pkey_export($privateKey, $privateKeyPem);
        
        $publicKeyDetails = openssl_pkey_get_details($privateKey);
        $publicKeyPem = $publicKeyDetails["key"];
        
        return [
            "private" => $privateKeyPem,
            "public" => $publicKeyPem,
        ];
    }
    
    public function sign(string $data, string $privateKey): string
    {
        openssl_sign($data, $signature, $privateKey, self::SIGNATURE_ALGO);
        return base64_encode($signature);
    }
    
    public function verify(string $data, string $signature, string $publicKey): bool
    {
        $result = openssl_verify($data, base64_decode($signature), $publicKey, self::SIGNATURE_ALGO);
        return $result === 1;
    }
    
    public static function generateUuid(): string
    {
        return sprintf("%04x%04x-%04x-%04x-%04x-%04x%04x%04x",
            random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );
    }
}
