<?php

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'boolean')]
    private bool $status = true;

    #[ORM\Column(type: 'float')]
    private float $tva = 0.0;

    #[ORM\ManyToOne(targetEntity: SubCategory::class, inversedBy: 'products')]
    private ?SubCategory $subCategory = null;

    #[ORM\OneToMany(
        mappedBy: 'product',
        targetEntity: ProductImage::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $images;

    #[ORM\OneToMany(
        mappedBy: 'product',
        targetEntity: ProductAttributeValue::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $attributes;

    #[ORM\OneToMany(
        mappedBy: 'product',
        targetEntity: ProductVariant::class,
        cascade: ['remove'],
        orphanRemoval: true
    )]
    private Collection $variants;

    #[ORM\ManyToOne(targetEntity: Rayon::class)]
    private ?Rayon $rayon = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'products')]
    private ?User $user = null;

    // Liaison corrigée : Boutique → Shop
    #[ORM\ManyToOne(targetEntity: Shop::class, inversedBy: 'products')]
    private ?Shop $shop = null;

    public function __construct()
    {
        $this->images = new ArrayCollection();
        $this->attributes = new ArrayCollection();
        $this->variants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSubCategory(): ?SubCategory
    {
        return $this->subCategory;
    }

    public function setSubCategory(?SubCategory $subCategory): static
    {
        $this->subCategory = $subCategory;
        return $this;
    }

    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ProductImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setProduct($this);
        }
        return $this;
    }

    public function removeImage(ProductImage $image): static
    {
        $this->images->removeElement($image);
        return $this;
    }

    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    public function addAttribute(ProductAttributeValue $attribute): static
    {
        if (!$this->attributes->contains($attribute)) {
            $this->attributes->add($attribute);
            $attribute->setProduct($this);
        }
        return $this;
    }

    public function removeAttribute(ProductAttributeValue $attribute): static
    {
        $this->attributes->removeElement($attribute);
        return $this;
    }

    public function getVariants(): Collection
    {
        return $this->variants;
    }

    public function addVariant(ProductVariant $variant): static
    {
        if (!$this->variants->contains($variant)) {
            $this->variants->add($variant);
            $variant->setProduct($this);
        }
        return $this;
    }

    public function removeVariant(ProductVariant $variant): static
    {
        $this->variants->removeElement($variant);
        return $this;
    }

    public function getRayon(): ?Rayon
    {
        return $this->rayon;
    }

    public function setRayon(?Rayon $rayon): self
    {
        $this->rayon = $rayon;
        return $this;
    }

    public function isStatus(): bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getTva(): float
    {
        return $this->tva;
    }

    public function setTva(float $tva): static
    {
        $this->tva = $tva;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    // Nouveaux getter/setter pour Shop
    public function getShop(): ?Shop
    {
        return $this->shop;
    }

    public function setShop(?Shop $shop): static
    {
        $this->shop = $shop;
        return $this;
    }
}
