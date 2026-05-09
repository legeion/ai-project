<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\SubCategory;
use App\Entity\Product;
use App\Entity\Rayon;
use App\Entity\ProductImage;
use App\Entity\AttributeProduct;
use App\Entity\AttributeValue;
use App\Entity\ProductAttributeValue;
use App\Entity\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:seed-products',
    description: 'Insère 5 produits concrets (T-shirt)'
)]
class SeedProductsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        // ========================
        // RAYONS
        // ========================
        $rayonsData = [
            'Homme',
            'Femme',
            'Fille',
            'Garçon',
            'Bébé Garçon',
            'Bébé Fille'
        ];

        $rayons = [];

        foreach ($rayonsData as $name) {
            $rayon = new Rayon();
            $rayon->setName($name);

            $this->em->persist($rayon);
            $rayons[$name] = $rayon;
        }

        // ========================
        // 1. CATEGORIES MULTI
        // ========================
        $categories = [];

        $categoriesData = [
            'T-SHIRT' => ['HOMME', 'FEMME'],
            'CHAUSSURES' => ['SPORT', 'VILLE'],
            'TELEPHONE' => ['ANDROID', 'IPHONE']
        ];

        foreach ($categoriesData as $parentName => $children) {
            $randomKey = array_rand($rayons);
            $parent = (new Category())
            ->setName($parentName)
            ->setRayon($rayons[$randomKey]);
            $this->em->persist($parent);


            foreach ($children as $childName) {
                $child = (new SubCategory())
                    ->setName($childName)
                    ->setCategory($parent);

                $this->em->persist($child);

                // 👉 IMPORTANT : on garde seulement les sous-catégories
                $categories[] = $child;
            }
        }
        // ========================
        // 2. ATTRIBUTES
        // ========================
        $taille = (new AttributeProduct())->setName('taille');
        $couleur = (new AttributeProduct())->setName('couleur');
        $marque = (new AttributeProduct())->setName('marque');
        $matiere = (new AttributeProduct())->setName('matiere');

        foreach ([$taille, $couleur, $marque, $matiere] as $attr) {
            $this->em->persist($attr);
        }

        // ========================
        // 3. VALUES
        // ========================
        $values = [
            'taille' => ['M', 'L', 'XL', 'XXL'],
            'couleur' => ['rouge', 'blanc', 'noir'],
            'marque' => ['Adidas', 'Nike'],
            'matiere' => ['coton', 'polyester']
        ];

        $valueEntities = [];

        foreach ($values as $attrName => $vals) {
            $attribute = $$attrName;

            foreach ($vals as $val) {
                $v = (new AttributeValue())
                    ->setValue($val)
                    ->setAttribute($attribute);

                $this->em->persist($v);
                $valueEntities[$attrName][$val] = $v;
            }
        }

        // ========================
        // 4. PRODUITS CONCRETS
        // ========================

        $productsData = [
            [
                'name' => 'T-shirt Adidas Rouge XXL',
                'taille' => 'XXL',
                'couleur' => 'rouge',
                'marque' => 'Adidas',
                'matiere' => 'coton'
            ],
            [
                'name' => 'T-shirt Nike Noir XL',
                'taille' => 'XL',
                'couleur' => 'noir',
                'marque' => 'Nike',
                'matiere' => 'polyester'
            ],
            [
                'name' => 'T-shirt Adidas Blanc L',
                'taille' => 'L',
                'couleur' => 'blanc',
                'marque' => 'Adidas',
                'matiere' => 'coton'
            ],
            [
                'name' => 'T-shirt Nike Rouge M',
                'taille' => 'M',
                'couleur' => 'rouge',
                'marque' => 'Nike',
                'matiere' => 'polyester'
            ],
            [
                'name' => 'T-shirt Adidas Noir XL',
                'taille' => 'XL',
                'couleur' => 'noir',
                'marque' => 'Adidas',
                'matiere' => 'coton'
            ]
        ];

        foreach ($productsData as $index => $data) {
            $product = new Product();
            $product->setName($data['name']);
            $randomCategory = $categories[array_rand($categories)];
            $product->setSubCategory($randomCategory);
            $product->setRayon($randomCategory->getCategory()?->getRayon());
            $product->setTva(0.18);

            $this->em->persist($product);

            // Images (2 images fixes)
            for ($i = 1; $i <= 2; $i++) {
                $img = new ProductImage();
                $img->setImagePath("product_" . ($index + 1) . "_$i.jpg");
                $img->setProduct($product);

                $this->em->persist($img);
            }

            // Attributs
            $this->addAttr($product, $taille, $valueEntities['taille'][$data['taille']]);
            $this->addAttr($product, $couleur, $valueEntities['couleur'][$data['couleur']]);
            $this->addAttr($product, $marque, $valueEntities['marque'][$data['marque']]);
            $this->addAttr($product, $matiere, $valueEntities['matiere'][$data['matiere']]);

            $variant = new ProductVariant();
            $variant->setProduct($product);
            $variant->setPriceHt(10000 + ($index * 1500));
            $variant->setPriceTtc((10000 + ($index * 1500)) * 1.18);
            $variant->setStock(10 + $index);
            $this->em->persist($variant);
        }

        $this->em->flush();

        $output->writeln('✅ 5 produits concrets insérés avec succès !');

        return Command::SUCCESS;
    }

    private function addAttr($product, $attribute, $value)
    {
        $pav = new ProductAttributeValue();
        $pav->setProduct($product);
        $pav->setAttribute($attribute);
        $pav->setValue($value);

        $this->em->persist($pav);
    }
}
