<?php
namespace App\Service;

use App\Entity\User;
use App\DTO\User\UserCreateDTO;
use App\DTO\User\UserResponseDTO;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

class AuthService
{
    private array $resetTokens = [];

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private TokenStorageInterface $tokenStorage,
        private ValidatorInterface $validator,
        private EmailService $emailService,
        private LoggerInterface $logger
    ) {}

    /**
     * Méthode 1: Connexion utilisateur
     */
    public function login(array $credentials): array
    {
        // Vérifier les credentials
        if (!isset($credentials['email']) || !isset($credentials['password'])) {
            throw new BadRequestHttpException('Email and password are required');
        }

        // Récupérer l'utilisateur
        $user = $this->em->getRepository(User::class)->findOneBy([
            'email' => $credentials['email']
        ]);

        // Vérifier si l'utilisateur existe
        if (!$user) {
            throw new UnauthorizedHttpException('', 'Invalid credentials');
        }

        // Vérifier le mot de passe
        if (!$this->passwordHasher->isPasswordValid($user, $credentials['password'])) {
            throw new UnauthorizedHttpException('', 'Invalid credentials');
        }

        // Vérifier si le compte est actif
        if (!$user->isActive()) {
            throw new UnauthorizedHttpException('', 'Account is disabled. Please contact administrator.');
        }

        // Générer le token JWT
        $token = $this->jwtManager->create($user);

        // Retourner la réponse
        return [
            'token' => $token,
            'user' => new UserResponseDTO($user),
            'expires_in' => 3600
        ];
    }

    /**
     * Méthode 2: Inscription utilisateur
     */
    public function register(UserCreateDTO $dto): array
    {
        // Vérifier si l'email existe déjà
        $existingUser = $this->em->getRepository(User::class)->findOneBy([
            'email' => $dto->email
        ]);

        if ($existingUser) {
            throw new BadRequestHttpException('Email already exists');
        }

        // Créer le nouvel utilisateur
        $user = new User();
        $user->setEmail($dto->email);
        $user->setFirstName($dto->firstName);
        $user->setLastName($dto->lastName);
        $user->setPhone($dto->phone ?? null);

        // Hasher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->password);
        $user->setPassword($hashedPassword);

        // Définir les rôles par défaut
        $roles = empty($dto->roles) ? ['ROLE_USER'] : $dto->roles;
        $user->setRoles($roles);

        // Valider l'utilisateur
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            throw new BadRequestHttpException(json_encode($errorMessages));
        }

        // Sauvegarder l'utilisateur
        $this->em->persist($user);
        $this->em->flush();

        // Envoyer l'email de bienvenue
        try {
            $this->emailService->sendWelcomeEmail($user);
            $emailStatus = 'sent';
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email bienvenue: ' . $e->getMessage());
            $emailStatus = 'failed';
        }

        // Générer le token JWT
        $token = $this->jwtManager->create($user);

        return [
            'token' => $token,
            'user' => new UserResponseDTO($user),
            'email_status' => $emailStatus,
            'message' => $emailStatus === 'sent'
                ? 'Inscription réussie. Un email de bienvenue vous a été envoyé.'
                : 'Inscription réussie mais l\'email de bienvenue n\'a pas pu être envoyé.'
        ];
    }

    /**
     * Méthode 3: Rafraîchir le token JWT
     */
    public function refreshToken(): array
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            throw new UnauthorizedHttpException('', 'User not found');
        }

        $newToken = $this->jwtManager->create($user);

        return [
            'token' => $newToken,
            'expires_in' => 3600
        ];
    }

    /**
     * Méthode 4: Déconnexion
     */
    public function logout(): array
    {
        // Pour JWT, le logout se fait côté client
        // On retourne juste un message de confirmation
        return [
            'message' => 'Déconnexion réussie. Veuillez supprimer votre token côté client.'
        ];
    }

    /**
     * Méthode 5: Vérifier si un email existe
     */
    public function checkEmailExists(string $email): bool
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        return $user !== null;
    }

    /**
     * Méthode 6: Mot de passe oublié
     */
    public function forgotPassword(string $email): array
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            return [
                'message' => 'Si cet email existe dans notre système, vous recevrez un lien de réinitialisation.'
            ];
        }

        $resetToken = bin2hex(random_bytes(32));

        $this->resetTokens[$resetToken] = [
            'user_id' => $user->getId(),
            'expires_at' => new \DateTimeImmutable('+1 hour')
        ];

        try {
            $this->emailService->sendPasswordResetEmail($user, $resetToken);

            return [
                'message' => 'Un lien de réinitialisation a été envoyé à votre adresse email.'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Erreur envoi email réinitialisation: ' . $e->getMessage());
            throw new \RuntimeException('Impossible d\'envoyer l\'email de réinitialisation. Veuillez réessayer plus tard.');
        }
    }

    /**
     * Méthode 7: Réinitialiser le mot de passe
     */
    public function resetPassword(string $token, string $newPassword): array
    {
        if (!isset($this->resetTokens[$token])) {
            throw new BadRequestHttpException('Token de réinitialisation invalide ou expiré');
        }

        $tokenData = $this->resetTokens[$token];

        if ($tokenData['expires_at'] < new \DateTimeImmutable()) {
            unset($this->resetTokens[$token]);
            throw new BadRequestHttpException('Le token de réinitialisation a expiré');
        }

        $user = $this->em->getRepository(User::class)->find($tokenData['user_id']);

        if (!$user) {
            throw new BadRequestHttpException('Utilisateur non trouvé');
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        unset($this->resetTokens[$token]);

        try {
            $this->emailService->sendPasswordChangedEmail($user);
        } catch (\Exception $e) {
            $this->logger->warning('Email confirmation non envoyé: ' . $e->getMessage());
        }

        return [
            'message' => 'Mot de passe réinitialisé avec succès. Vous pouvez maintenant vous connecter.'
        ];
    }

    /**
     * Méthode 8: Changer le mot de passe (utilisateur connecté)
     */
    public function changePassword(string $currentPassword, string $newPassword): array
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            throw new UnauthorizedHttpException('', 'Vous devez être connecté');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
            throw new BadRequestHttpException('Le mot de passe actuel est incorrect');
        }

        if ($currentPassword === $newPassword) {
            throw new BadRequestHttpException('Le nouveau mot de passe doit être différent de l\'ancien');
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        try {
            $this->emailService->sendPasswordChangedEmail($user);
        } catch (\Exception $e) {
            $this->logger->warning('Email confirmation non envoyé: ' . $e->getMessage());
        }

        $newToken = $this->jwtManager->create($user);

        return [
            'token' => $newToken,
            'message' => 'Mot de passe changé avec succès. Un email de confirmation vous a été envoyé.'
        ];
    }

    /**
     * Méthode 9: Obtenir l'utilisateur actuel
     */
    public function getCurrentUser(): ?User
    {
        $token = $this->tokenStorage->getToken();

        if (!$token) {
            return null;
        }

        $user = $token->getUser();

        if (!$user instanceof User) {
            return null;
        }

        return $user;
    }

    /**
     * Méthode 10: Vérifier si l'utilisateur a un rôle
     */
    public function hasRole(string $role): bool
    {
        $user = $this->getCurrentUser();

        if (!$user) {
            return false;
        }

        return in_array($role, $user->getRoles());
    }
}
