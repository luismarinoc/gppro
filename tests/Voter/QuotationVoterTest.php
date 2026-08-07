<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Voter;

use App\Entity\Customer;
use App\Entity\Quotation;
use App\Entity\Team;
use App\Entity\User;
use App\Voter\QuotationVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

#[CoversClass(QuotationVoter::class)]
class QuotationVoterTest extends AbstractVoterTestCase
{
    public function testQuotationRequiresDedicatedViewPermission(): void
    {
        $quotation = new Quotation();
        $quotation->setCustomer(new Customer('Acme'));
        $user = self::getUser(1, User::ROLE_ADMIN);

        $this->assertVote($user, $quotation, 'view_quotation', VoterInterface::ACCESS_DENIED);
    }

    public function testQuotationUsesCustomerTeamAccess(): void
    {
        $team = new Team('Sales');
        $customer = new Customer('Acme');
        $customer->addTeam($team);
        $quotation = new Quotation();
        $quotation->setCustomer($customer);
        $user = new User();
        $user->addRole(User::ROLE_TEAMLEAD);
        $team->addTeamlead($user);

        $permissions = ['ROLE_TEAMLEAD' => ['view_quotation', 'edit_quotation', 'send_quotation', 'convert_quotation']];
        $token = new UsernamePasswordToken($user, 'test', $user->getRoles());
        $voter = new QuotationVoter($this->getRolePermissionManager($permissions, true));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $quotation, ['view_quotation']));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $quotation, ['edit_quotation']));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $quotation, ['send_quotation']));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, $quotation, ['convert_quotation']));
    }

    private function assertVote(User $user, Quotation $quotation, string $attribute, int $expected): void
    {
        $token = new UsernamePasswordToken($user, 'test', $user->getRoles());
        $voter = $this->getVoter(QuotationVoter::class);

        self::assertSame($expected, $voter->vote($token, $quotation, [$attribute]));
    }
}
