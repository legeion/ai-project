<?php
namespace App\DTO\User;
use App\Entity\User;

class UserResponseDTO
{
    public int $id;
    public string $email;
    public string $firstName;
    public string $lastName;
    public ?string $phone;
    public array $roles;
    public bool $isActive;
    public string $createdAt;
    public ?string $updatedAt;
    public array $shops = [];

    public function __construct(User $user)
    {
        $this->id = $user->getId();
        $this->email = $user->getEmail();
        $this->firstName = $user->getFirstName();
        $this->lastName = $user->getLastName();
        $this->phone = $user->getPhone();
        $this->roles = $user->getRoles();
        $this->isActive = $user->isActive();
        $this->createdAt = $user->getCreatedAt()->format('Y-m-d H:i:s');
        $this->updatedAt = $user->getUpdatedAt()?->format('Y-m-d H:i:s');
    }
}
