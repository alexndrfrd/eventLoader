<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Entity\User;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

final class InMemoryUserProvider implements UserProviderInterface
{
    /**
     * Hardcoded test users (for development/testing)
     * Password: "test" for all users
     * Hash generated with: password_hash('test', PASSWORD_BCRYPT)
     */
    private const USERS = [
        'admin' => [
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // "test"
            'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
        ],
        'user' => [
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // "test"
            'roles' => ['ROLE_USER'],
        ],
    ];

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        if (!isset(self::USERS[$identifier])) {
            throw new UserNotFoundException(sprintf('Username "%s" does not exist.', $identifier));
        }

        $userData = self::USERS[$identifier];

        return new User(
            $identifier,
            $userData['password'],
            $userData['roles']
        );
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}
