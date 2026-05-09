<?php

namespace App\Entity;

use App\Repository\AttributeValueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttributeValueRepository::class)]
class AttributeValue
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $value;

    #[ORM\ManyToOne(targetEntity: AttributeProduct::class, inversedBy: 'values')]
    private AttributeProduct $attribute;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

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
}
