<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // The security firewall (json_login) intercepts this route before it reaches here.
        throw new \LogicException('This endpoint is handled by the security firewall.');
    }
}
