<?php

namespace App\Controller\Api;

use App\Entity\Rayon;
use App\Entity\Category;
use App\Entity\AttributeProduct;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/categories')]
class CategoryController extends AbstractController
{
    // =========================
    //  LISTE (avec hiérarchie)
    // =========================
    #[Route('', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $categories = $em->getRepository(Category::class)->findAll();
        $data = [];
        foreach ($categories as $cat) {
            $subCategories = [];
            foreach ($cat->getSubCategories() as $subCategory) {
                $subCategories[] = [
                    'id' => $subCategory->getId(),
                    'name' => $subCategory->getName(),
                    'status' => $subCategory->isStatus(),
                    'icon' => $subCategory->getIcon(),
                    'categoryId' => $cat->getId()
                ];
            }

            $data[] = [
                'id' => $cat->getId(),
                'name' => $cat->getName(),
                'status' => $cat->isStatus(),
                'icon' => $cat->getIcon(),
                'rayon' => $cat->getRayon() ? [
                    'id' => $cat->getRayon()->getId(),
                    'name' => $cat->getRayon()->getName(),
                ] : null,
                'attributes' => array_map(
                    static fn (AttributeProduct $attribute) => [
                        'id' => $attribute->getId(),
                        'name' => $attribute->getName(),
                        'values' => array_map(
                            static fn ($value) => [
                                'id' => $value->getId(),
                                'value' => $value->getValue()
                            ],
                            $attribute->getValues()->toArray()
                        )
                    ],
                    $cat->getAttributes()->toArray()
                ),
                'children' => $subCategories,
                'subCategories' => $subCategories
            ];
        }

        return $this->json($data);
    }

    // =========================
    // CREER
    // =========================
    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $category = new Category();
        $category->setName($data['name']);

        if (!empty($data['rayon'])) {
            $rayon = $em->getRepository(Rayon::class)->find($data['rayon']);
            $category->setRayon($rayon);
        }

        if (array_key_exists('status', $data)) {
            $category->setStatus((bool) $data['status']);
        }

        if (array_key_exists('icon', $data)) {
            $category->setIcon($data['icon'] ?: null);
        }

        $em->persist($category);
        $em->flush();

        return $this->json(['message' => 'Catégorie créée']);
    }


    // =========================
    // UPDATE
    // =========================
    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $category = $em->getRepository(Category::class)->find($id);

        if (!$category) {
            return $this->json(['error' => 'Catégorie non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!empty($data['name'])) {
            $category->setName($data['name']);
        }

        if (array_key_exists('rayon', $data)) {
            $rayon = $data['rayon']
                ? $em->getRepository(Rayon::class)->find($data['rayon'])
                : null;

            $category->setRayon($rayon);
        }

        if (array_key_exists('status', $data)) {
            $category->setStatus((bool) $data['status']);
        }

        if (array_key_exists('icon', $data)) {
            $category->setIcon($data['icon'] ?: null);
        }

        $em->flush();

        return $this->json(['message' => 'Catégorie mise à jour']);
    }

    // =========================
    //  DELETE
    // =========================
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Category $category, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($category);
        $em->flush();

        return $this->json(['message' => 'Catégorie supprimée']);
    }

    #[Route('/{id}/attributes', methods: ['GET'])]
    public function getAttributes(int $id, EntityManagerInterface $em): JsonResponse
    {
        $category = $em->getRepository(Category::class)->find($id);

        if (!$category) {
            return $this->json(['error' => 'Catégorie non trouvée'], 404);
        }

        $attributes = array_map(
            static fn (AttributeProduct $attribute) => [
                'id' => $attribute->getId(),
                'name' => $attribute->getName(),
                'values' => array_map(
                    static fn ($value) => [
                        'id' => $value->getId(),
                        'value' => $value->getValue()
                    ],
                    $attribute->getValues()->toArray()
                )
            ],
            $category->getAttributes()->toArray()
        );

        return $this->json($attributes);
    }

    #[Route('/{id}/attributes', methods: ['POST'])]
    public function assignAttributes(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $category = $em->getRepository(Category::class)->find($id);

        if (!$category) {
            return $this->json(['error' => 'Catégorie non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $attributeIds = $data['attributes'] ?? [];

        foreach ($attributeIds as $attributeId) {
            $attribute = $em->getRepository(AttributeProduct::class)->find($attributeId);

            if ($attribute) {
                $category->addAttribute($attribute);
            }
        }

        $em->flush();

        return $this->json(['message' => 'Attributs de la catégorie mis à jour']);
    }

}
