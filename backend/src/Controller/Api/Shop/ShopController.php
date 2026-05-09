<?php

namespace App\Controller\Api\Shop;

use App\Service\ShopService;
use App\DTO\Shop\ShopCreateDTO;
use App\DTO\Shop\ShopUpdateDTO;
use App\Security\Voter\ShopVoter;
use App\Entity\Shop;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
//use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[Route('/api/shops')]
class ShopController extends AbstractController
{
    public function __construct(
        private ShopService $shopService,
        private AuthorizationCheckerInterface $authorizationChecker
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 30);

        $filters = [
            'category' => $request->query->get('category'),
            'floor' => $request->query->get('floor'),
            'isActive' => $request->query->get('isActive')
        ];

        $filters = array_filter($filters);
        $shops = $this->shopService->getAll($page, $limit, $filters);

        // Filtrer les boutiques selon les droits de l'utilisateur
        $filteredShops = array_filter($shops, function($shop) {
            return $this->isGranted(ShopVoter::VIEW, $shop);
        });

        return $this->json([
            'data' => array_values($filteredShops),
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters
        ]);
    }

    #[Route('', methods: ['POST'])]
    //#[IsGranted('ROLE_USER')]
    public function create(#[MapRequestPayload] ShopCreateDTO $dto): JsonResponse
    {
        // Vérifier si l'utilisateur a le droit de créer une boutique
        //$this->denyAccessUnlessGranted(ShopVoter::CREATE);

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $shop = $this->shopService->create($dto, $currentUser);

        return $this->json($shop, Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $shop = $this->shopService->getOne($id, $currentUser);

        // Utiliser le voter pour vérifier si l'utilisateur peut voir la boutique
        if (!$this->isGranted(ShopVoter::VIEW, $shop)) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        // Pour les informations sensibles, vérifier avec un attribut différent
        $includeSensitive = $this->isGranted(ShopVoter::VIEW_SENSITIVE, $shop);

        return $this->json([
            'shop' => $shop,
            'include_sensitive' => $includeSensitive
        ]);
    }

    #[Route('/{id}', methods: ['PUT'])]
    //#[IsGranted('ROLE_USER')]
    public function update(int $id, #[MapRequestPayload] ShopUpdateDTO $dto): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Récupérer la boutique pour vérifier les droits
        $shop = $this->shopService->getOne($id, $currentUser);

        // Vérifier si l'utilisateur a le droit de modifier cette boutique
        if (!$this->isGranted(ShopVoter::EDIT, $shop)) {
            return $this->json(['error' => 'You can only update your own shops'], Response::HTTP_FORBIDDEN);
        }

        $updatedShop = $this->shopService->update($id, $dto, $currentUser);

        return $this->json($updatedShop);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    //#[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Récupérer la boutique pour vérifier les droits
        $shop = $this->shopService->getOne($id, $currentUser);

        // Vérifier si l'utilisateur a le droit de supprimer cette boutique
        if (!$this->isGranted(ShopVoter::DELETE, $shop)) {
            return $this->json(['error' => 'Only admins can delete shops'], Response::HTTP_FORBIDDEN);
        }

        $this->shopService->delete($id, $currentUser);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/my-shops', methods: ['GET'])]
    //#[IsGranted('ROLE_USER')]
    public function myShops(): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $shops = $this->shopService->getMyShops($currentUser);

        // Filtrer selon les droits
        $filteredShops = array_filter($shops, function($shop) use ($currentUser) {
            return $this->isGranted(ShopVoter::VIEW, $shop);
        });

        return $this->json(array_values($filteredShops));
    }

    #[Route('/{id}/sensitive-data', methods: ['GET'])]
    //#[IsGranted('ROLE_USER')]
    public function getSensitiveData(int $id): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $shop = $this->shopService->getOne($id, $currentUser);

        // Vérifier l'accès aux données sensibles
        if (!$this->isGranted(ShopVoter::VIEW_SENSITIVE, $shop)) {
            return $this->json(['error' => 'Access denied to sensitive data'], Response::HTTP_FORBIDDEN);
        }

        // Données sensibles (exemple)
        $sensitiveData = [
            'monthly_revenue' => 15000.50,
            'visitor_count' => 1250,
            'staff_count' => 5,
            'contract_end_date' => '2025-12-31',
            'rent_amount' => 2500.00
        ];

        return $this->json($sensitiveData);
    }
}
