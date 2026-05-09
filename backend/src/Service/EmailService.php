<?php
// src/Service/EmailService.php

namespace App\Service;

use App\Entity\User;
use App\Entity\Shop;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $appUrl
    ) {}

    /**
     * Envoi de l'email de bienvenue
     */
    public function sendWelcomeEmail(User $user): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address('noreply@centrecommercial.com', 'Centre Commercial'))
                ->to(new Address($user->getEmail(), $user->getFirstName() . ' ' . $user->getLastName()))
                ->subject('Bienvenue sur la plateforme du Centre Commercial')
                ->htmlTemplate('emails/welcome.html.twig')
                ->context([
                    'user' => $user,
                    'loginUrl' => $this->appUrl . '/login',
                    'appUrl' => $this->appUrl
                ]);

            $this->mailer->send($email);
            $this->logger->info('Email de bienvenue envoyé à ' . $user->getEmail());

        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email bienvenue: ' . $e->getMessage());
            throw new \RuntimeException('Impossible d\'envoyer l\'email de bienvenue: ' . $e->getMessage());
        }
    }

    /**
     * Envoi de l'email de réinitialisation de mot de passe
     */
    public function sendPasswordResetEmail(User $user, string $resetToken): void
    {
        try {
            $resetUrl = $this->appUrl . '/reset-password?token=' . $resetToken;

            $email = (new TemplatedEmail())
                //->from(new Address('noreply@centrecommercial.com', 'Centre Commercial'))
                ->from(new Address(
                $_ENV['MAILER_FROM_EMAIL'] ?? 'no-reply@alo-service.com',
                $_ENV['MAILER_FROM_NAME'] ?? 'Alo Centre Commercial'
            ))
                //->to($user->getEmail())
                ->to(new Address($user->getEmail(), $user->getFirstName() . ' ' . $user->getLastName()))
                ->subject('Réinitialisation de votre mot de passe')
                ->htmlTemplate('emails/reset_password.html.twig')
                ->context([
                    'user' => $user,
                    'resetUrl' => $resetUrl,
                    'expirationHours' => 1,
                    'appUrl' => $this->appUrl
                ]);

            $this->mailer->send($email);
            $this->logger->info('Email réinitialisation envoyé à ' . $user->getEmail());

        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email réinitialisation: ' . $e->getMessage());
            throw new \RuntimeException('Impossible d\'envoyer l\'email de réinitialisation: ' . $e->getMessage());
        }


    }

    /**
     * Envoi de l'email de confirmation de changement de mot de passe
     */
    public function sendPasswordChangedEmail(User $user): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address('noreply@centrecommercial.com', 'Centre Commercial'))
                ->to(new Address($user->getEmail(), $user->getFirstName() . ' ' . $user->getLastName()))
                ->subject('Votre mot de passe a été modifié')
                ->htmlTemplate('emails/password_changed.html.twig')
                ->context([
                    'user' => $user,
                    'loginUrl' => $this->appUrl . '/login',
                    'changeDate' => new \DateTimeImmutable(),
                    'appUrl' => $this->appUrl
                ]);

            $this->mailer->send($email);
            $this->logger->info('Email confirmation changement envoyé à ' . $user->getEmail());

        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email confirmation: ' . $e->getMessage());
            // Non critique, on continue
        }
    }

    /**
     * Envoi de l'email de création de boutique
     */
    public function sendShopCreatedEmail(User $user, Shop $shop): void
    {
        try {
            $shopUrl = $this->appUrl . '/shops/' . $shop->getId();

            $email = (new TemplatedEmail())
                ->from(new Address('noreply@centrecommercial.com', 'Centre Commercial'))
                ->to(new Address($user->getEmail(), $user->getFirstName() . ' ' . $user->getLastName()))
                ->subject('Votre boutique "' . $shop->getName() . '" a été créée')
                ->htmlTemplate('emails/shop_created.html.twig')
                ->context([
                    'user' => $user,
                    'shop' => $shop,
                    'shopUrl' => $shopUrl,
                    'dashboardUrl' => $this->appUrl . '/dashboard',
                    'appUrl' => $this->appUrl
                ]);

            $this->mailer->send($email);
            $this->logger->info('Email création boutique envoyé à ' . $user->getEmail());

        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email création boutique: ' . $e->getMessage());
            // Non critique
        }
    }
}
