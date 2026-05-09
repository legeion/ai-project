<?php

namespace App\Controller\Api;

use App\Entity\AttributeProduct;
use App\Entity\AttributeValue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/attributes')]
class AttributeController extends AbstractController
{
    // =========================
    //  LISTE ATTRIBUTS + VALEURS
    // =========================
    #[Route('', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $attributes = $em->getRepository(AttributeProduct::class)->findAll();

        $data = [];

        foreach ($attributes as $attr) {
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

    // =========================
    // CREER ATTRIBUT
    // =========================
    #[Route('', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $attr = new AttributeProduct();
        $attr->setName($data['name']);

        $em->persist($attr);
        $em->flush();

        return $this->json(['message' => 'Attribut créé']);
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
        $attribute = $em->getRepository(AttributeProduct::class)->find($id);

        if (!$attribute) {
            return $this->json(['error' => 'Attribut non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!empty($data['name'])) {
            $attribute->setName($data['name']);
        }

        $em->flush();

        return $this->json(['message' => 'Attribut mis à jour']);
    }

    // =========================
    // AJOUT VALEUR
    // =========================
    #[Route('/{id}/values', methods: ['POST'])]
    public function addValue(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $attribute = $em->getRepository(AttributeProduct::class)->find($id);

        $data = json_decode($request->getContent(), true);

        $value = new AttributeValue();
        $value->setValue($data['value']);
        $value->setAttribute($attribute);

        $em->persist($value);
        $em->flush();

        return $this->json(['message' => 'Valeur ajoutée']);
    }

    // =========================
    // UPDATE VALUE
    // =========================
    #[Route('/values/{id}', methods: ['PUT'])]
    public function updateValue(
        int $id,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $value = $em->getRepository(AttributeValue::class)->find($id);

        if (!$value) {
            return $this->json(['error' => 'Valeur non trouvée'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!empty($data['value'])) {
            $value->setValue($data['value']);
        }

        $em->flush();

        return $this->json(['message' => 'Valeur mise à jour']);
    }

    #[Route('/values/{id}', methods: ['DELETE'])]
    public function deleteValue(int $id, EntityManagerInterface $em): JsonResponse
    {
        $value = $em->getRepository(AttributeValue::class)->find($id);

        if (!$value) {
            return $this->json(['error' => 'Valeur non trouvée'], 404);
        }

        $attribute = $value->getAttribute();
        if ($attribute) {
            $attribute->removeValue($value);
        }

        $em->remove($value);
        $em->flush();

        return $this->json(['message' => 'Valeur supprimée']);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $em): JsonResponse
    {
        $attribute = $em->getRepository(AttributeProduct::class)->find($id);

        if (!$attribute) {
            return $this->json(['error' => 'Attribut non trouvé'], 404);
        }

        foreach ($attribute->getCategories()->toArray() as $category) {
            $category->removeAttribute($attribute);
        }

        foreach ($attribute->getValues()->toArray() as $value) {
            $attribute->removeValue($value);
            $em->remove($value);
        }

        $em->remove($attribute);
        $em->flush();

        return $this->json(['message' => 'Attribut supprimé']);
    }

}
