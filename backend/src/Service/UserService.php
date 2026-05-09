<?php
namespace App\Service;

use App\Entity\User;
use App\DTO\User\UserCreateDTO;
use App\DTO\User\UserUpdateDTO;
use App\DTO\User\UserResponseDTO;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class UserService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function create(UserCreateDTO $dto): UserResponseDTO
    {
        // Vérifier si l'email existe déjà
        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $dto->email]);
        if ($existing) {
            throw new BadRequestHttpException('Email already exists');
        }

        $user = new User();
        $user->setEmail($dto->email);
        $user->setFirstName($dto->firstName);
        $user->setLastName($dto->lastName);
        $user->setPhone($dto->phone);

        // Hasher le mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->password);
        $user->setPassword($hashedPassword);

        // Gérer les rôles
        if (!empty($dto->roles)) {
            $user->setRoles($dto->roles);
        }

        $this->em->persist($user);
        $this->em->flush();

        return new UserResponseDTO($user);
    }

    public function update(int $id, UserUpdateDTO $dto): UserResponseDTO
    {
        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        if ($dto->email && $dto->email !== $user->getEmail()) {
            $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $dto->email]);
            if ($existing) {
                throw new BadRequestHttpException('Email already exists');
            }
            $user->setEmail($dto->email);
        }

        if ($dto->firstName) $user->setFirstName($dto->firstName);
        if ($dto->lastName) $user->setLastName($dto->lastName);
        if ($dto->phone !== null) $user->setPhone($dto->phone);
        if ($dto->isActive !== null) $user->setIsActive($dto->isActive);

        if ($dto->password) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->password);
            $user->setPassword($hashedPassword);
        }

        if (!empty($dto->roles)) {
            $user->setRoles($dto->roles);
        }

        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return new UserResponseDTO($user);
    }

    public function delete(int $id): void
    {
        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        $this->em->remove($user);
        $this->em->flush();
    }

    public function getOne(int $id): UserResponseDTO
    {
        $user = $this->em->getRepository(User::class)->find($id);
        if (!$user) {
            throw new NotFoundHttpException('User not found');
        }

        return new UserResponseDTO($user);
    }

    public function getAll(int $page = 1, int $limit = 20): array
    {
        $users = $this->em->getRepository(User::class)->findBy(
            [],
            ['id' => 'DESC'],
            $limit,
            ($page - 1) * $limit
        );

        return array_map(fn($user) => new UserResponseDTO($user), $users);
    }
}
