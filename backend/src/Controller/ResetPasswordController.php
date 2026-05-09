<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ResetPasswordController extends AbstractController
{
    #[Route('/reset-password/{token}', name: 'api_auth_reset_password_form')]
    public function resetPasswordForm(string $token): Response
    {
        // Afficher un formulaire HTML pour réinitialiser le mot de passe
        return $this->render('reset_password.html.twig', [
            'token' => $token
        ]);
    }
}
