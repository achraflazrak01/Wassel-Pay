<?php

namespace App\Services;

class CryptoService
{
    private const CURVE = 'prime256v1';
    private const CIPHER = 'aes-256-gcm';
    private const SIGNATURE_ALGO = OPENSSL_ALGO_SHA256;
    
    public function generateKeyPair(): array
    {
        $config = [
            'curve_name' => self::CURVE,
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];
        
        $privateKey = openssl_pkey_new($config);
        
        if ($privateKey === false) {
            throw new \Exception('Erreur génération clé ECC');
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
            throw new \Exception('Clé publique invalide');
        }
        $result = openssl_verify($data, base64_decode($signature), $key, self::SIGNATURE_ALGO);
        return $result === 1;
    }
    
    private function deriveSharedSecret(string $privateKey, string $peerPublicKey): string
    {
        $priv = openssl_pkey_get_private($privateKey);
        $pub = openssl_pkey_get_public($peerPublicKey);
        if ($priv === false || $pub === false) {
            throw new \Exception('Impossible de dériver le secret');
        }
        $secret = openssl_pkey_derive($pub, $priv, 256);
        if ($secret === false) {
            throw new \Exception('Erreur dérivation ECDH');
        }
        return $secret;
    }
    
    public function encryptHybrid(string $data, string $recipientPublicKey): array
    {
        $sessionKey = random_bytes(32);
        $nonce = random_bytes(12);
        
        $ciphertext = openssl_encrypt($data, self::CIPHER, $sessionKey, OPENSSL_RAW_DATA, $nonce, $tag);
        
        if ($ciphertext === false) {
            throw new \Exception('Erreur chiffrement AES');
        }
        
        $config = [
            'curve_name' => self::CURVE,
            'private_key_type' => OPENSSL_KEYTYPE_EC,
        ];
        $ephemeralKey = openssl_pkey_new($config);
        openssl_pkey_export($ephemeralKey, $ephemeralPrivate);
        $ephemeralDetails = openssl_pkey_get_details($ephemeralKey);
        $ephemeralPublic = $ephemeralDetails['key'];
        
        $sharedSecret = $this->deriveSharedSecret($ephemeralPrivate, $recipientPublicKey);
        $iv = random_bytes(12);
        $encryptedKey = openssl_encrypt($sessionKey, self::CIPHER, $sharedSecret, OPENSSL_RAW_DATA, $iv, $tagKey);
        
        $encryptedSessionKey = base64_encode($ephemeralPublic) . ':' . base64_encode($iv) . ':' . base64_encode($encryptedKey) . ':' . base64_encode($tagKey);
        
        return [
            'encrypted_session_key' => base64_encode($encryptedSessionKey),
            'nonce' => base64_encode($nonce),
            'ciphertext' => base64_encode($ciphertext),
            'tag' => base64_encode($tag),
        ];
    }
    
    public function decryptHybrid(array $packet, string $recipientPrivateKey): string
    {
        $encryptedData = base64_decode($packet['encrypted_session_key']);
        $parts = explode(':', $encryptedData);
        if (count($parts) !== 4) {
            throw new \Exception('Format invalide');
        }
        
        list($ephemeralPublicB64, $ivB64, $encryptedKeyB64, $tagKeyB64) = $parts;
        
        $ephemeralPublic = base64_decode($ephemeralPublicB64);
        $iv = base64_decode($ivB64);
        $encryptedKey = base64_decode($encryptedKeyB64);
        $tagKey = base64_decode($tagKeyB64);
        
        $sharedSecret = $this->deriveSharedSecret($recipientPrivateKey, $ephemeralPublic);
        $sessionKey = openssl_decrypt($encryptedKey, self::CIPHER, $sharedSecret, OPENSSL_RAW_DATA, $iv, $tagKey);
        
        if ($sessionKey === false) {
            throw new \Exception('Erreur déchiffrement clé session');
        }
        
        $plaintext = openssl_decrypt(
            base64_decode($packet['ciphertext']),
            self::CIPHER,
            $sessionKey,
            OPENSSL_RAW_DATA,
            base64_decode($packet['nonce']),
            base64_decode($packet['tag'])
        );
        
        if ($plaintext === false) {
            throw new \Exception('Erreur déchiffrement AES');
        }
        
        return $plaintext;
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
