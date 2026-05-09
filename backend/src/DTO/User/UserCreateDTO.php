<?php

namespace App\DTO\User;

use Symfony\Component\Validator\Constraints as Assert;

class UserCreateDTO
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 6)]
    public string $password;

    #[Assert\NotBlank]
    public string $firstName;

    #[Assert\NotBlank]
    public string $lastName;

    #[Assert\Length(min: 10, max: 20)]
    public ?string $phone = null;

    public array $roles = [];
}
