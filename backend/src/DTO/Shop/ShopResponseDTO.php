<?php
namespace App\DTO\Shop;

use App\Entity\Shop;

class ShopResponseDTO
{
    public int $id;
    public string $name;
    public string $code;
    public ?string $description;
    public string $category;
    public string $floor;
    public float $surface;
    public ?string $phone;
    public ?string $logoUrl;
    public ?array $openingHours;
    public bool $isActive;
    public string $createdAt;
    public ?string $updatedAt;
    public ?array $owner;
    public int $productsCount;

    public function __construct(Shop $shop)
    {
        $this->id = $shop->getId();
        $this->name = $shop->getName();
        $this->code = $shop->getCode();
        $this->description = $shop->getDescription();
        $this->category = $shop->getCategory();
        $this->floor = $shop->getFloor();
        $this->surface = $shop->getSurface();
        $this->phone = $shop->getPhone();
        $this->logoUrl = $shop->getLogoUrl();
        $this->openingHours = $shop->getOpeningHours();
        $this->isActive = $shop->isActive();
        $this->createdAt = $shop->getCreatedAt()->format('Y-m-d H:i:s');
        $this->updatedAt = $shop->getUpdatedAt()?->format('Y-m-d H:i:s');

        if ($shop->getOwner()) {
            $this->owner = [
                'id' => $shop->getOwner()->getId(),
                'firstName' => $shop->getOwner()->getFirstName(),
                'lastName' => $shop->getOwner()->getLastName(),
                'email' => $shop->getOwner()->getEmail()
            ];
        }

        $this->productsCount = $shop->getProducts()->count();
    }
}
