<?php

namespace App\Entity;

use App\Repository\ProductVariantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductVariantRepository::class)]
class ProductVariant
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    private Product $product;

    #[ORM\Column(name: 'price_ht')]
    private float $priceHt = 0.0;

    #[ORM\Column(name: 'price_ttc')]
    private float $priceTtc = 0.0;

    #[ORM\Column]
    private int $stock;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrice(): ?float
    {
        return $this->priceTtc;
    }

    public function setPrice(float $price): static
    {
        $this->priceHt = $price;
        $this->priceTtc = $price;

        return $this;
    }

    public function getPriceHt(): ?float
    {
        return $this->priceHt;
    }

    public function setPriceHt(float $priceHt): static
    {
        $this->priceHt = $priceHt;

        return $this;
    }

    public function getPriceTtc(): ?float
    {
        return $this->priceTtc;
    }

    public function setPriceTtc(float $priceTtc): static
    {
        $this->priceTtc = $priceTtc;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }
}
