<?php
/**
 * CryptoManager - Handles encryption/decryption of sensitive data
 * Uses AES-256-CBC encryption for at-rest security of credentials
 */
class CryptoManager
{
    private static $algorithm = 'AES-256-CBC';
    
    /**
     * Get encryption key from environment or use default (should be set in .env)
     * For production, ALWAYS use a proper key management system
     */
    private static function getEncryptionKey()
    {
        // Try to get from environment variable first
        $envKey = getenv('CALLOWAY_ENCRYPTION_KEY');
        if ($envKey) {
            return hash('sha256', $envKey, true);
        }
        
        // Fallback to reading from secure config file (NOT in version control)
        $keyFile = __DIR__ . '/.encryption_key';
        if (file_exists($keyFile)) {
            $key = file_get_contents($keyFile);
            if ($key) {
                return hash('sha256', trim($key), true);
            }
        }
        
        // Development mode warning - NEVER use in production
        error_log('WARNING: Using default encryption key. Set CALLOWAY_ENCRYPTION_KEY environment variable for production.');
        return hash('sha256', 'calloway-pharmacy-default-key-change-in-production', true);
    }
    
    /**
     * Encrypt sensitive data (e.g., SMTP passwords)
     * 
     * @param string $plaintext Data to encrypt
     * @return string Base64-encoded ciphertext with IV prepended
     */
    public static function encrypt($plaintext)
    {
        if (empty($plaintext)) {
            return '';
        }
        
        $key = self::getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$algorithm));
        
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::$algorithm,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($ciphertext === false) {
            error_log('Encryption failed: ' . openssl_error_string());
            return '';
        }
        
        // Prepend IV to ciphertext and base64 encode
        return base64_encode($iv . $ciphertext);
    }
    
    /**
     * Decrypt encrypted data
     * 
     * @param string $encrypted Base64-encoded ciphertext with IV prepended
     * @return string|false Plaintext or false on failure
     */
    public static function decrypt($encrypted)
    {
        if (empty($encrypted)) {
            return '';
        }
        
        $key = self::getEncryptionKey();
        $data = base64_decode($encrypted, true);
        
        if ($data === false) {
            error_log('Failed to decode base64 in decryption');
            return false;
        }
        
        $ivLength = openssl_cipher_iv_length(self::$algorithm);
        
        if (strlen($data) < $ivLength) {
            error_log('Invalid encrypted data: too short');
            return false;
        }
        
        $iv = substr($data, 0, $ivLength);
        $ciphertext = substr($data, $ivLength);
        
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::$algorithm,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($plaintext === false) {
            error_log('Decryption failed: ' . openssl_error_string());
            return false;
        }
        
        return $plaintext;
    }
    
    /**
     * Hash a password using bcrypt (for password storage)
     * Uses PASSWORD_BCRYPT with cost 12
     * 
     * @param string $password Plain password
     * @return string Hashed password
     */
    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
    
    /**
     * Verify password against hash
     * 
     * @param string $password Plain password
     * @param string $hash Stored hash
     * @return bool True if password matches hash
     */
    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }
}
?>
