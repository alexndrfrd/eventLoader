<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_auth_')]
final class AuthController extends AbstractController
{
    /**
     * Login endpoint - handled by json_login firewall
     * 
     * POST /api/login
     * Body: {"username": "admin", "password": "test"}
     * 
     * Success Response (200):
     * {
     *   "token": "eyJ0eXAiOiJKV1QiLCJhbGc..."
     * }
     * 
     * Error Response (401):
     * {
     *   "code": 401,
     *   "message": "Invalid credentials."
     * }
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // This method is intercepted by json_login in security.yaml
        // It never actually executes - the firewall handles authentication
        // and returns the JWT token via lexik_jwt_authentication handlers
        
        return $this->json([
            'message' => 'This should not be reached - handled by json_login firewall'
        ]);
    }

    /**
     * Documentation endpoint - lists available test users
     * GET /api/auth/users
     */
    #[Route('/auth/users', name: 'test_users', methods: ['GET'])]
    public function testUsers(): JsonResponse
    {
        return $this->json([
            'test_users' => [
                [
                    'username' => 'admin',
                    'password' => 'test',
                    'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
                ],
                [
                    'username' => 'user',
                    'password' => 'test',
                    'roles' => ['ROLE_USER'],
                ],
            ],
            'note' => 'These are test users. In production, use proper user management.',
        ]);
    }
}
