<?php
namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\AttributeProductRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttributeProductRepository::class)]
class AttributeProduct
{
#[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\OneToMany(mappedBy: 'attribute', targetEntity: AttributeValue::class)]
    private Collection $values;

    #[ORM\ManyToMany(targetEntity: Category::class, mappedBy: 'attributes')]
    private Collection $categories;

    public function __construct()
    {
        $this->values = new ArrayCollection();
        $this->categories = new ArrayCollection();
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

    /**
     * @return Collection<int, AttributeValue>
     */
    public function getValues(): Collection
    {
        return $this->values;
    }

    public function addValue(AttributeValue $value): static
    {
        if (!$this->values->contains($value)) {
            $this->values->add($value);
            $value->setAttribute($this);
        }

        return $this;
    }

    public function removeValue(AttributeValue $value): static
    {
        if ($this->values->removeElement($value)) {
            // set the owning side to null (unless already changed)
            if ($value->getAttribute() === $this) {
                $value->setAttribute(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->addAttribute($this);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        if ($this->categories->removeElement($category)) {
            $category->removeAttribute($this);
        }

        return $this;
    }

}
