<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Expense;

use App\Entity\Customer;
use App\Entity\Expense;
use App\Entity\ExpenseAllocation;
use App\Entity\Project;
use App\Entity\Quotation;
use App\Expense\ExpenseCrossChargeService;
use App\Tests\Repository\AbstractRepositoryTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(ExpenseCrossChargeService::class)]
#[Group('integration')]
class ExpenseCrossChargeServiceTest extends AbstractRepositoryTestCase
{
    private function getSut(): ExpenseCrossChargeService
    {
        $em = $this->getEntityManager();
        \assert($em instanceof EntityManagerInterface);

        return new ExpenseCrossChargeService($em);
    }

    private function createCustomer(): Customer
    {
        $customer = new Customer('Cross-charge test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $this->getEntityManager()->persist($customer);

        return $customer;
    }

    private function createProject(Customer $customer): Project
    {
        $project = new Project();
        $project->setName('Cross-charge test project ' . uniqid());
        $project->setCustomer($customer);
        $this->getEntityManager()->persist($project);

        return $project;
    }

    private function createDraftQuotation(Customer $customer, ?Project $project, string $currency = Quotation::CURRENCY_CLP): Quotation
    {
        $quotation = (new Quotation())->setCustomer($customer)->setCurrency($currency);
        if (null !== $project) {
            $quotation->setProject($project);
        }
        $this->getEntityManager()->persist($quotation);

        return $quotation;
    }

    private function createApprovedAllocation(Project $project, int $amountClp = 150000): ExpenseAllocation
    {
        $expense = new Expense();
        $expense->setDescription('Consulting fee');
        $expense->setAmount($amountClp);
        $expense->setExpenseDate(new \DateTimeImmutable('2026-08-01'));
        $allocation = (new ExpenseAllocation())->setProject($project)->setPercentage('100.00')->setAmountClp($amountClp);
        $expense->addAllocation($allocation);
        $expense->submitForApproval(1);
        $expense->clearLevel(1);
        $this->getEntityManager()->persist($expense);
        $this->getEntityManager()->flush();

        return $allocation;
    }

    public function testChargeApprovedAllocationAddsQuotationLineAndMarksAllocationCharged(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer);
        $quotation = $this->createDraftQuotation($customer, $project);
        $allocation = $this->createApprovedAllocation($project, 150000);
        $this->getEntityManager()->flush();

        $line = $this->getSut()->charge($allocation, $quotation);

        self::assertTrue($allocation->isCharged());
        self::assertSame($line, $allocation->getQuotationLine());
        self::assertContains($line, $quotation->getLines()->toArray());
        // Exact QuotationLine shape (task 6.1): qty '1', unitPrice=amountClp,
        // description "<expense description> (<expense date Y-m-d>)". No
        // quotation pricing/line logic is duplicated - QuotationLine is
        // created and added via the existing App\Entity\Quotation aggregate.
        self::assertSame('1', $line->getQuantity());
        self::assertSame('150000.0000', $line->getUnitPrice());
        self::assertSame('Consulting fee (2026-08-01)', $line->getDescription());
    }

    public function testChargeIsRejectedWhenQuotationCurrencyIsNotClp(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer);
        $quotation = $this->createDraftQuotation($customer, $project, Quotation::CURRENCY_USD);
        $allocation = $this->createApprovedAllocation($project);
        $this->getEntityManager()->flush();

        $this->expectException(\DomainException::class);

        $this->getSut()->charge($allocation, $quotation);
    }

    public function testChargeIsRejectedWhenAllocationAlreadyCharged(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer);
        $quotation = $this->createDraftQuotation($customer, $project);
        $allocation = $this->createApprovedAllocation($project);
        $this->getEntityManager()->flush();
        $this->getSut()->charge($allocation, $quotation);

        $secondQuotation = $this->createDraftQuotation($customer, $project);
        $this->getEntityManager()->flush();

        $this->expectException(\DomainException::class);

        $this->getSut()->charge($allocation, $secondQuotation);
    }

    public function testChargeIsRejectedWhenQuotationProjectDoesNotMatchAllocation(): void
    {
        $customer = $this->createCustomer();
        $project = $this->createProject($customer);
        $otherProject = $this->createProject($customer);
        $quotation = $this->createDraftQuotation($customer, $otherProject);
        $allocation = $this->createApprovedAllocation($project);
        $this->getEntityManager()->flush();

        $this->expectException(\DomainException::class);

        $this->getSut()->charge($allocation, $quotation);
    }
}
