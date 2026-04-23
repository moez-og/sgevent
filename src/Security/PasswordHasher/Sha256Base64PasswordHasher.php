<?php

namespace App\Security\PasswordHasher;

use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

final class Sha256Base64PasswordHasher implements PasswordHasherInterface
{
    private NativePasswordHasher $nativePasswordHasher;

    public function __construct()
    {
        $this->nativePasswordHasher = new NativePasswordHasher();
    }

    public function hash(string $plainPassword): string
    {
        return base64_encode(hash('sha256', $plainPassword, true));
    }

    public function verify(string $hashedPassword, string $plainPassword): bool
    {
        // Keep compatibility for already stored bcrypt/argon hashes.
        if ($this->isNativeHash($hashedPassword)) {
            return $this->nativePasswordHasher->verify($hashedPassword, $plainPassword);
        }

        return hash_equals($hashedPassword, $this->hash($plainPassword));
    }

    public function needsRehash(string $hashedPassword): bool
    {
        return $this->isNativeHash($hashedPassword) || strlen($hashedPassword) !== 44;
    }

    private function isNativeHash(string $hashedPassword): bool
    {
        return str_starts_with($hashedPassword, '$argon2i$')
            || str_starts_with($hashedPassword, '$argon2id$')
            || str_starts_with($hashedPassword, '$2a$')
            || str_starts_with($hashedPassword, '$2b$')
            || str_starts_with($hashedPassword, '$2y$');
    }
}
