<?php
namespace App\DTO\Shop;

use Symfony\Component\Validator\Constraints as Assert;

class ShopCreateDTO
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 150)]
    public string $name;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 50)]
    public string $code;

    #[Assert\Length(max: 255)]
    public ?string $description = null;

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['Boutique', 'Restaurant', 'Service', 'Loisirs'])]
    public string $category;

    #[Assert\NotBlank]
    public string $floor;

    #[Assert\NotBlank]
    #[Assert\Positive]
    public float $surface;

    public ?string $phone = null;

    public ?string $logoUrl = null;

    public ?array $openingHours = [];
}
