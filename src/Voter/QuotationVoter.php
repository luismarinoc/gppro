<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Voter;

use App\Entity\Quotation;
use App\Entity\User;
use App\Security\RolePermissionManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Quotation> */
final class QuotationVoter extends Voter
{
    private const ATTRIBUTES = ['view_quotation', 'edit_quotation', 'delete_quotation', 'send_quotation', 'convert_quotation'];

    public function __construct(private readonly RolePermissionManager $permissions)
    {
    }

    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, self::ATTRIBUTES, true);
    }

    public function supportsType(string $subjectType): bool
    {
        return str_contains($subjectType, Quotation::class);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Quotation && $this->supportsAttribute($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof Quotation) {
            return false;
        }

        $customer = $subject->getCustomer();
        if (!$this->permissions->hasRolePermission($user, 'view_quotation') || $customer === null) {
            return false;
        }

        if (!$this->permissions->checkTeamAccessCustomer($customer, $user)) {
            return false;
        }

        if ($subject->getProject() !== null && !$this->permissions->checkTeamAccessProject($subject->getProject(), $user)) {
            return false;
        }

        return match ($attribute) {
            'view_quotation' => true,
            'edit_quotation' => $this->permissions->hasRolePermission($user, 'edit_quotation'),
            'delete_quotation' => $this->permissions->hasRolePermission($user, 'delete_quotation'),
            'send_quotation' => $this->permissions->hasRolePermission($user, 'send_quotation'),
            'convert_quotation' => $this->permissions->hasRolePermission($user, 'convert_quotation'),
            default => false,
        };
    }
}
