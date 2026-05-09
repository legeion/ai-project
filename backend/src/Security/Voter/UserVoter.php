<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class UserVoter extends Voter
{
    public const VIEW = 'USER_VIEW';
    public const EDIT = 'USER_EDIT';
    public const DELETE = 'USER_DELETE';
    public const VIEW_ROLES = 'USER_VIEW_ROLES';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::VIEW_ROLES])) {
            return false;
        }

        if (!$subject instanceof User) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof UserInterface) {
            return false;
        }

        // Admin a tous les droits
        if (in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            return true;
        }

        /** @var User $targetUser */
        $targetUser = $subject;

        return match($attribute) {
            self::VIEW => $currentUser === $targetUser,
            self::EDIT => $currentUser === $targetUser,
            self::DELETE => false, // Seul admin peut supprimer
            self::VIEW_ROLES => in_array('ROLE_ADMIN', $currentUser->getRoles()),
            default => false,
        };
    }
}
