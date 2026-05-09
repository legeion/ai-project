<?php

namespace App\Controller\Api;

use App\Entity\Rayon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/rayons')]
class RayonController extends AbstractController
{
    #[Route('', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $rayons = $em->getRepository(Rayon::class)->findAll();

        $data = [];

        foreach ($rayons as $rayon) {
            $categories = [];

            foreach ($rayon->getCategories() as $cat) {
                $categories[] = [
                    'id' => $cat->getId(),
                    'name' => $cat->getName()
                ];
            }

            $data[] = [
                'id' => $rayon->getId(),
                'name' => $rayon->getName(),
                'categories' => $categories
            ];
        }

        return $this->json($data);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        $rayon = $em->getRepository(Rayon::class)->find($id);

        if (!$rayon) {
            return $this->json(['error' => 'Rayon non trouvé'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!empty($data['name'])) {
            $rayon->setName($data['name']);
        }

        $em->flush();

        return $this->json(['message' => 'Rayon mis à jour']);
    }

}
