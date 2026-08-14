<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Voter;

use App\Entity\Invoice;
use App\Entity\User;
use App\Invoice\InvoicePaymentApprovalPolicy;
use App\Security\RolePermissionManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A voter to check permissions on Invoice.
 *
 * @extends Voter<string, Invoice>
 */
final class InvoiceVoter extends Voter
{
    /**
     * support rules based on the given invoice
     */
    private const ALLOWED_ATTRIBUTES = [
        'view_invoice',
        'edit_invoice',
        'delete_invoice',
        'approve_invoice_payment',
        'reject_invoice_payment',
    ];

    public function __construct(
        private readonly RolePermissionManager $rolePermissionManager,
        private readonly InvoicePaymentApprovalPolicy $approvalPolicy,
    ) {
    }

    public function supportsAttribute(string $attribute): bool
    {
        return \in_array($attribute, self::ALLOWED_ATTRIBUTES, true);
    }

    public function supportsType(string $subjectType): bool
    {
        return str_contains($subjectType, Invoice::class);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Invoice && $this->supportsAttribute($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if (!$subject instanceof Invoice) {
            return false;
        }

        // approve/reject bypass the customer-team-access gate below entirely
        // (mirrors ExpenseVoter's approve_expense/reject_expense): eligibility
        // is fully determined by InvoicePaymentApprovalPolicy's own role/
        // named-approver/four-eyes checks, independent of customer team scope.
        if ($attribute === 'approve_invoice_payment') {
            return $this->approvalPolicy->canApprove($subject, $user);
        }

        if ($attribute === 'reject_invoice_payment') {
            return $this->approvalPolicy->canReject($subject, $user);
        }

        // every user needs to be able to view-invoices in order to perform any action
        if (!$this->rolePermissionManager->hasRolePermission($user, 'view_invoice')) {
            return false;
        }

        // this should never happen
        if ($subject->getCustomer() === null) {
            return false;
        }

        // check if the user is allowed to see the invoice customer
        if (!$this->rolePermissionManager->checkTeamAccessCustomer($subject->getCustomer(), $user)) {
            return false;
        }

        // all good here
        if ($attribute === 'view_invoice') {
            return true;
        }

        // there is no edit_invoice, so we only check if the user can create an invoice for the customer
        if ($attribute === 'edit_invoice') {
            return $this->rolePermissionManager->hasRolePermission($user, 'create_invoice');
        }

        if ($attribute === 'delete_invoice') {
            return $this->rolePermissionManager->hasRolePermission($user, 'delete_invoice');
        }

        return false;
    }
}
