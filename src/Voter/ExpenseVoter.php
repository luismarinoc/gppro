<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Voter;

use App\Entity\Expense;
use App\Entity\User;
use App\Expense\ExpenseApprovalPolicy;
use App\Security\RolePermissionManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * `create_expense` is deliberately absent here: it has no `Expense` subject
 * yet, so it is resolved by the automatically-registered RolePermissionVoter
 * (subject === null) once `create_expense` is a registered permission
 * (design D2 / gppro.yaml EXPENSES set).
 *
 * @extends Voter<string, Expense>
 */
final class ExpenseVoter extends Voter
{
    private const ATTRIBUTES = ['view_expense', 'edit_expense', 'delete_expense', 'charge_expense', 'approve_expense', 'reject_expense'];

    public function __construct(
        private readonly RolePermissionManager $permissions,
        private readonly ExpenseApprovalPolicy $approvalPolicy,
    ) {
    }

    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, self::ATTRIBUTES, true);
    }

    public function supportsType(string $subjectType): bool
    {
        return str_contains($subjectType, Expense::class);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Expense && $this->supportsAttribute($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$subject instanceof Expense) {
            return false;
        }

        return match ($attribute) {
            'view_expense' => $this->canView($subject, $user),
            'edit_expense' => $this->permissions->hasRolePermission($user, 'edit_expense') && $subject->isEditable(),
            'delete_expense' => $this->permissions->hasRolePermission($user, 'delete_expense') && \in_array($subject->getStatus(), [Expense::STATUS_DRAFT, Expense::STATUS_REJECTED], true),
            'charge_expense' => $this->permissions->hasRolePermission($user, 'charge_expense') && $subject->isApproved(),
            'approve_expense' => $this->approvalPolicy->canApprove($subject, $user),
            'reject_expense' => $this->approvalPolicy->canReject($subject, $user),
            default => false,
        };
    }

    /**
     * Design D1/D2: cheapest-first order — admin bypass, then creator
     * (O(1)), then a team-accessible allocation (in-memory loop over
     * checkTeamAccessProject(), the ProjectVoter precedent), then the
     * DB-backed approver carve-out last.
     */
    private function canView(Expense $expense, User $user): bool
    {
        if ($user->canSeeAllData()) {
            return true;
        }

        if ($expense->getCreatedBy() === $user) {
            return true;
        }

        foreach ($expense->getAllocations() as $allocation) {
            $project = $allocation->getProject();
            if (null !== $project && $this->permissions->checkTeamAccessProject($project, $user)) {
                return true;
            }
        }

        return $this->approvalPolicy->canApprove($expense, $user);
    }
}
