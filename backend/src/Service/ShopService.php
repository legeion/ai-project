<?php
namespace App\Service;

use App\Entity\Shop;
use App\Entity\User;
use App\DTO\Shop\ShopCreateDTO;
use App\DTO\Shop\ShopUpdateDTO;
use App\DTO\Shop\ShopResponseDTO;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ShopService
{
    public function __construct(
        private EntityManagerInterface $em,
        private EmailService $emailService  // Ajout du service d'email
    ) {}

    public function create(ShopCreateDTO $dto, ?User $owner = null): ShopResponseDTO
    {
        $existing = $this->em->getRepository(Shop::class)->findOneBy(['code' => $dto->code]);
        if ($existing) {
            throw new BadRequestHttpException('Shop code already exists');
        }

        $shop = new Shop();
        $shop->setName($dto->name);
        $shop->setCode($dto->code);
        $shop->setDescription($dto->description);
        $shop->setCategory($dto->category);
        $shop->setFloor($dto->floor);
        $shop->setSurface($dto->surface);
        $shop->setPhone($dto->phone);
        $shop->setLogoUrl($dto->logoUrl);
        $shop->setOpeningHours($dto->openingHours ?? []);
        $shop->setOwner($owner);

        $this->em->persist($shop);
        $this->em->flush();

        if ($owner) {
            try {
                $this->emailService->sendShopCreatedEmail($owner, $shop);
            } catch (\Exception $e) {}
        }

        return new ShopResponseDTO($shop);
    }

    public function update(int $id, ShopUpdateDTO $dto, User $currentUser): ShopResponseDTO
    {
        $shop = $this->em->getRepository(Shop::class)->find($id);
        if (!$shop) {
            throw new NotFoundHttpException('Shop not found');
        }

        // Vérification des droits
        if (!in_array('ROLE_ADMIN', $currentUser->getRoles()) && $shop->getOwner()->getId() !== $currentUser->getId()) {
            throw new AccessDeniedHttpException('You can only update your own shops');
        }

        if ($dto->name) $shop->setName($dto->name);

        if ($dto->code && $dto->code !== $shop->getCode()) {
            $existing = $this->em->getRepository(Shop::class)->findOneBy(['code' => $dto->code]);
            if ($existing) {
                throw new BadRequestHttpException('Shop code already exists');
            }
            $shop->setCode($dto->code);
        }

        if ($dto->description !== null) $shop->setDescription($dto->description);
        if ($dto->category) $shop->setCategory($dto->category);
        if ($dto->floor) $shop->setFloor($dto->floor);
        if ($dto->surface) $shop->setSurface($dto->surface);
        if ($dto->phone !== null) $shop->setPhone($dto->phone);
        if ($dto->logoUrl !== null) $shop->setLogoUrl($dto->logoUrl);
        if ($dto->openingHours !== null) $shop->setOpeningHours($dto->openingHours);
        if ($dto->isActive !== null) $shop->setIsActive($dto->isActive);

        $shop->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return new ShopResponseDTO($shop);
    }

    public function delete(int $id, User $currentUser): void
    {
        $shop = $this->em->getRepository(Shop::class)->find($id);
        if (!$shop) {
            throw new NotFoundHttpException('Shop not found');
        }

        // Seul admin peut supprimer
        if (!in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            throw new AccessDeniedHttpException('Only admins can delete shops');
        }

        $this->em->remove($shop);
        $this->em->flush();
    }

    public function getOne(int $id, User $currentUser): ShopResponseDTO
    {
        $shop = $this->em->getRepository(Shop::class)->find($id);
        if (!$shop) {
            throw new NotFoundHttpException('Shop not found');
        }

        // Vérification des droits pour les détails sensibles
        if (!in_array('ROLE_ADMIN', $currentUser->getRoles()) && $shop->getOwner()->getId() !== $currentUser->getId()) {
            // Version publique sans données sensibles
            return new ShopResponseDTO($shop);
        }

        return new ShopResponseDTO($shop);
    }

    public function getAll(int $page = 1, int $limit = 30, ?array $filters = []): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('s')
           ->from(Shop::class, 's')
           ->orderBy('s.name', 'ASC');

        if (!empty($filters['category'])) {
            $qb->andWhere('s.category = :category')
               ->setParameter('category', $filters['category']);
        }

        if (!empty($filters['floor'])) {
            $qb->andWhere('s.floor = :floor')
               ->setParameter('floor', $filters['floor']);
        }

        if (!empty($filters['isActive'])) {
            $qb->andWhere('s.isActive = :isActive')
               ->setParameter('isActive', $filters['isActive']);
        }

        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $shops = $qb->getQuery()->getResult();

        return array_map(fn($shop) => new ShopResponseDTO($shop), $shops);
    }

    public function getMyShops(User $owner): array
    {
        $shops = $this->em->getRepository(Shop::class)->findBy(['owner' => $owner]);
        return array_map(fn($shop) => new ShopResponseDTO($shop), $shops);
    }
}
