<?php

declare(strict_types=1);

namespace App\Tests\Helper;

use App\Domain\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Helper for creating test users with proper password hashing
 */
final class TestUserHelper
{
    public static function createTestUser(
        UserPasswordHasherInterface $hasher,
        string $username = 'testuser',
        string $password = 'testpass',
        array $roles = ['ROLE_USER']
    ): User {
        $user = new User($username, '', $roles);
        $hashedPassword = $hasher->hashPassword($user, $password);
        
        return new User($username, $hashedPassword, $roles);
    }

    /**
     * Create user without hasher (for unit tests)
     */
    public static function createSimpleUser(
        string $username = 'testuser',
        array $roles = ['ROLE_USER']
    ): User {
        return new User($username, 'hashed_password', $roles);
    }
}
