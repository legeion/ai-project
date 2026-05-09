<?php
// src/Controller/Api/AuthController.php

namespace App\Controller\Api\User;

use App\DTO\User\UserCreateDTO;
use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(private AuthService $authService) {}

    /**
     * Connexion utilisateur
     */
    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $result = $this->authService->login($data);

        return $this->json($result, Response::HTTP_OK);
    }

    /**
     * Inscription utilisateur
     */
    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(#[MapRequestPayload] UserCreateDTO $dto): JsonResponse
    {
        $result = $this->authService->register($dto);

        return $this->json($result, Response::HTTP_CREATED);
    }

    /**
     * Rafraîchir le token JWT
     */
    #[Route('/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(): JsonResponse
    {
        $result = $this->authService->refreshToken();

        return $this->json($result, Response::HTTP_OK);
    }

    /**
     * Déconnexion
     */
    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        $result = $this->authService->logout();

        return $this->json($result, Response::HTTP_OK);
    }

    /**
     * Changer le mot de passe
     */
    #[Route('/change-password', name: 'api_auth_change_password', methods: ['POST'])]
    public function changePassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['current_password']) || !isset($data['new_password'])) {
            return $this->json([
                'error' => 'current_password and new_password are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->authService->changePassword(
            $data['current_password'],
            $data['new_password']
        );

        return $this->json($result, Response::HTTP_OK);
    }

    /**
     * Vérifier si un email existe
     */
    #[Route('/check-email', name: 'api_auth_check_email', methods: ['GET'])]
    public function checkEmail(Request $request): JsonResponse
    {
        $email = $request->query->get('email');

        if (!$email) {
            return $this->json(['error' => 'Email parameter is required'], Response::HTTP_BAD_REQUEST);
        }

        $exists = $this->authService->checkEmailExists($email);

        return $this->json(['exists' => $exists], Response::HTTP_OK);
    }

    /**
     * Mot de passe oublié
     */
    #[Route('/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'])) {
            return $this->json(['error' => 'Email is required'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->authService->forgotPassword($data['email']);

        return $this->json($result, Response::HTTP_OK);
    }

    /**
     * Réinitialiser le mot de passe
     */
    #[Route('/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['token']) || !isset($data['new_password'])) {
            return $this->json([
                'error' => 'token and new_password are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->authService->resetPassword($data['token'], $data['new_password']);

        return $this->json($result, Response::HTTP_OK);
    }

    /**
     * Obtenir l'utilisateur actuel
     */
    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->authService->getCurrentUser();

        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($user, Response::HTTP_OK);
    }
}
