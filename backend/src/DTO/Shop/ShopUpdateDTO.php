<?php
namespace App\DTO\Shop;

use Symfony\Component\Validator\Constraints as Assert;

class ShopUpdateDTO
{
    #[Assert\Length(min: 2, max: 150)]
    public ?string $name = null;

    #[Assert\Length(min: 2, max: 50)]
    public ?string $code = null;

    #[Assert\Length(max: 255)]
    public ?string $description = null;

    #[Assert\Choice(choices: ['Boutique', 'Restaurant', 'Service', 'Loisirs'])]
    public ?string $category = null;

    public ?string $floor = null;

    #[Assert\Positive]
    public ?float $surface = null;

    public ?string $phone = null;

    public ?string $logoUrl = null;

    public ?array $openingHours = null;

    public ?bool $isActive = null;
}
