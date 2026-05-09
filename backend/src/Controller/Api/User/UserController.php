<?php

namespace App\Controller\Api\User;

use App\Service\UserService;
use App\DTO\User\UserCreateDTO;
use App\DTO\User\UserUpdateDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
//use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/users')]
class UserController extends AbstractController
{
    public function __construct(private UserService $userService) {}

    #[Route('', methods: ['GET'])]
    //#[IsGranted('ROLE_ADMIN')]
    public function index(Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 20);

        $users = $this->userService->getAll($page, $limit);

        return $this->json([
            'data' => $users,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    #[Route('', methods: ['POST'])]
    //#[IsGranted('ROLE_ADMIN')]
    public function create(#[MapRequestPayload] UserCreateDTO $dto): JsonResponse
    {
        $user = $this->userService->create($dto);
        return $this->json($user, Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    //#[IsGranted('ROLE_ADMIN')]
    public function show(int $id): JsonResponse
    {
        $user = $this->userService->getOne($id);
        return $this->json($user);
    }

    #[Route('/{id}', methods: ['PUT'])]
    //#[IsGranted('ROLE_ADMIN')]
    public function update(int $id, #[MapRequestPayload] UserUpdateDTO $dto): JsonResponse
    {
        $user = $this->userService->update($id, $dto);
        return $this->json($user);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    //#[IsGranted('ROLE_ADMIN')]
    public function delete(int $id): JsonResponse
    {
        $this->userService->delete($id);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/me', methods: ['GET'])]
    //#[IsGranted('ROLE_USER')]
    public function me(): JsonResponse
    {
        $user = $this->userService->getOne($this->getUser()->getId());
        return $this->json($user);
    }
}
