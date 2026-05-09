<?php

namespace App\Controller\Api;

use App\Entity\AttributeProduct;
use App\Entity\Category;
use App\Entity\SubCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/sub-categories')]
class SubCategoryController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $subCategories = $em->getRepository(SubCategory::class)->findAll();

        $data = [];

        foreach ($subCategories as $subCategory) {
            $data[] = [
                'id' => $subCategory->getId(),
                'name' => $subCategory->getName(),
                'status' => $subCategory->isStatus(),
                'icon' => $subCategory->getIcon(),
                'category' => $subCategory->getCategory() ? [
                    'id' => $subCategory->getCategory()?->getId(),
                    'name' => $subCategory->getCategory()?->getName(),
                    'rayon' => $subCategory->getCategory()?->getRayon() ? [
                        'id' => $subCategory->getCategory()?->getRayon()?->getId(),
                        'name' => $subCategory->getCategory()?->getRayon()?->getName()
                    ] : null
                ] : null
            ];
        }

        return $this->json($data);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $subCategory = new SubCategory();
        $subCategory->setName($data['name'] ?? '');
        $subCategory->setStatus((bool) ($data['status'] ?? true));
        $subCategory->setIcon($data['icon'] ?? null);

        if (!empty($data['category'])) {
            $category = $em->getRepository(Category::class)->find($data['category']);
            $subCategory->setCategory($category);
        }

        $em->persist($subCategory);
        $em->flush();

        return $this->json(['message' => 'Sous-catégorie créée']);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $subCategory = $em->getRepository(SubCategory::class)->find($id);

        if (!$subCategory) {
            return $this->json(['error' => 'Sous-catégorie non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!empty($data['name'])) {
            $subCategory->setName($data['name']);
        }

        if (array_key_exists('status', $data)) {
            $subCategory->setStatus((bool) $data['status']);
        }

        if (array_key_exists('icon', $data)) {
            $subCategory->setIcon($data['icon'] ?: null);
        }

        if (array_key_exists('category', $data)) {
            $category = $data['category']
                ? $em->getRepository(Category::class)->find($data['category'])
                : null;
            $subCategory->setCategory($category);
        }

        $em->flush();

        return $this->json(['message' => 'Sous-catégorie mise à jour']);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(SubCategory $subCategory, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($subCategory);
        $em->flush();

        return $this->json(['message' => 'Sous-catégorie supprimée']);
    }

    #[Route('/{id}/attributes', methods: ['GET'])]
    public function getAttributes(int $id, EntityManagerInterface $em): JsonResponse
    {
        $subCategory = $em->getRepository(SubCategory::class)->find($id);

        if (!$subCategory) {
            return $this->json(['error' => 'Sous-catégorie non trouvée'], 404);
        }

        $data = [];

        foreach ($subCategory->getAttributes() as $attr) {
            $values = [];

            foreach ($attr->getValues() as $val) {
                $values[] = [
                    'id' => $val->getId(),
                    'value' => $val->getValue()
                ];
            }

            $data[] = [
                'id' => $attr->getId(),
                'name' => $attr->getName(),
                'values' => $values
            ];
        }

        return $this->json($data);
    }

    #[Route('/{id}/attributes', methods: ['POST'])]
    public function assignAttributes(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $subCategory = $em->getRepository(SubCategory::class)->find($id);

        if (!$subCategory) {
            return $this->json(['error' => 'Sous-catégorie non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);

        foreach (($data['attributes'] ?? []) as $attrId) {
            $attribute = $em->getRepository(AttributeProduct::class)->find($attrId);
            if ($attribute) {
                $subCategory->addAttribute($attribute);
            }
        }

        $em->flush();

        return $this->json(['message' => 'Attributs assignés à la sous-catégorie']);
    }

    #[Route('/category/{categoryId}', methods: ['GET'])]
    public function getByCategory(int $categoryId, EntityManagerInterface $em): JsonResponse
    {
        $category = $em->getRepository(Category::class)->find($categoryId);

        if (!$category) {
            return $this->json(['error' => 'Catégorie non trouvée'], 404);
        }
        $subCategories = $em->getRepository(SubCategory::class)
            ->findBy(['category' => $category]);

        $data = [];

        foreach ($subCategories as $subCategory) {
            $data[] = [
                'id' => $subCategory->getId(),
                'name' => $subCategory->getName(),
                'status' => $subCategory->isStatus(),
                'icon' => $subCategory->getIcon(),
                'category' => [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                    'rayon' => $category->getRayon() ? [
                        'id' => $category->getRayon()->getId(),
                        'name' => $category->getRayon()->getName()
                    ] : null
                ]
            ];
        }

        return $this->json($data);
    }
}
