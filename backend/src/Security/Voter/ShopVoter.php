<?php

namespace App\Security\Voter;

use App\Entity\Shop;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class ShopVoter extends Voter
{
    public const VIEW = 'SHOP_VIEW';
    public const EDIT = 'SHOP_EDIT';
    public const DELETE = 'SHOP_DELETE';
    public const CREATE = 'SHOP_CREATE';
    public const VIEW_SENSITIVE = 'SHOP_VIEW_SENSITIVE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Vérifier si l'attribut est supporté
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::CREATE, self::VIEW_SENSITIVE])) {
            return false;
        }

        // Pour CREATE, le subject peut être null
        if ($attribute === self::CREATE) {
            return true;
        }

        // Pour les autres attributs, le subject doit être une instance de Shop
        if (!$subject instanceof Shop) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // Si l'utilisateur n'est pas connecté
        if (!$user instanceof UserInterface) {
            return false;
        }

        // Vérifier si l'utilisateur est admin (a tous les droits)
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Vérifier les différents attributs
        return match($attribute) {
            self::VIEW => $this->canView($subject, $user),
            self::EDIT => $this->canEdit($subject, $user),
            self::DELETE => $this->canDelete($subject, $user),
            self::CREATE => $this->canCreate($user),
            self::VIEW_SENSITIVE => $this->canViewSensitive($subject, $user),
            default => false,
        };
    }

    private function canView(Shop $shop, UserInterface $user): bool
    {
        // Tout le monde peut voir les boutiques actives
        if ($shop->isActive()) {
            return true;
        }

        // Les boutiques inactives ne sont visibles que par leur propriétaire ou admin
        return $shop->getOwner() === $user;
    }

    private function canEdit(Shop $shop, UserInterface $user): bool
    {
        // Seul le propriétaire peut modifier sa boutique
        // Les admins sont déjà gérés plus haut
        return $shop->getOwner() === $user;
    }

    private function canDelete(Shop $shop, UserInterface $user): bool
    {
        // La suppression est réservée aux admins uniquement
        // Les propriétaires ne peuvent pas supprimer leur boutique
        return false;
    }

    private function canCreate(UserInterface $user): bool
    {
        // Tout utilisateur connecté peut créer une boutique
        // (ROLE_USER suffit, les admins peuvent aussi)
        return in_array('ROLE_USER', $user->getRoles()) || in_array('ROLE_TENANT', $user->getRoles());
    }

    private function canViewSensitive(Shop $shop, UserInterface $user): bool
    {
        // Les informations sensibles (chiffre d'affaires, données confidentielles)
        // ne sont visibles que par le propriétaire ou admin
        return $shop->getOwner() === $user;
    }
}
