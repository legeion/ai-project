<?php

namespace App\Entity;

use App\Repository\ProductAttributeValueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductAttributeValueRepository::class)]
class ProductAttributeValue
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'attributes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: AttributeProduct::class)]
    private AttributeProduct $attribute;

    #[ORM\ManyToOne(targetEntity: AttributeValue::class)]
    private AttributeValue $value;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAttribute(): ?AttributeProduct
    {
        return $this->attribute;
    }

    public function setAttribute(?AttributeProduct $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function getValue(): ?AttributeValue
    {
        return $this->value;
    }

    public function setValue(?AttributeValue $value): static
    {
        $this->value = $value;

        return $this;
    }
}
