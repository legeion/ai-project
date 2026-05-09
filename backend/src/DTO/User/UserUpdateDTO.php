<?php

namespace App\DTO\User;

use Symfony\Component\Validator\Constraints as Assert;

class UserUpdateDTO
{
    #[Assert\Email]
    public ?string $email = null;

    #[Assert\Length(min: 6)]
    public ?string $password = null;

    public ?string $firstName = null;

    public ?string $lastName = null;

    #[Assert\Length(min: 10, max: 20)]
    public ?string $phone = null;

    public ?bool $isActive = null;

    public array $roles = [];
}
