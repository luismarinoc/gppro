<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Twig;

use App\Entity\Expense;
use App\Entity\ExpenseAllocation;
use App\Entity\ExpenseApproval;
use App\Entity\Project;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Twig\Environment;

#[Group('integration')]
class ExpensePdfTest extends KernelTestCase
{
    private function render(Expense $expense, string $locale): string
    {
        self::bootKernel();
        /** @var Environment $twig */
        $twig = self::getContainer()->get('twig');
        /** @var RequestStack $stack */
        $stack = self::getContainer()->get('request_stack');
        $request = new Request();
        $request->setLocale($locale);
        $stack->push($request);
        $translator = self::getContainer()->get('translator');
        self::assertInstanceOf(LocaleAwareInterface::class, $translator);
        $translator->setLocale($locale);

        return $twig->render('expense/pdf.html.twig', ['expense' => $expense, 'defaultLogo' => null]);
    }

    private function buildExpense(bool $withDetails): Expense
    {
        $expense = (new Expense())
            ->setDescription('Office rent')
            ->setAmount(150000)
            ->setCurrency(Expense::CURRENCY_CLP)
            ->setExpenseDate(new \DateTimeImmutable('2026-09-15'))
            ->setCategory(Expense::CATEGORY_RENT);
        $id = new \ReflectionProperty(Expense::class, 'id');
        $id->setValue($expense, 7);

        if (!$withDetails) {
            return $expense;
        }

        $author = new User();
        $author->setAlias('Ana Author');
        $approver = new User();
        $approver->setAlias('Bruno Approver');
        $expense->setCreatedBy($author);

        $project = new Project();
        $project->setName('Platform Project');
        $expense->addAllocation(
            (new ExpenseAllocation())->setProject($project)->setPercentage('100')->setAmountClp(150000)
        );

        $approval = (new ExpenseApproval())
            ->setExpense($expense)
            ->setLevel(1)
            ->setApprovalAttempt(1)
            ->setDecision(ExpenseApproval::DECISION_APPROVED)
            ->setApprovedBy($approver)
            ->setApprovedAt(new \DateTimeImmutable('2026-09-16'));
        $expense->getApprovals()->add($approval);

        return $expense;
    }

    /**
     * @return array<string, array{0: string, 1: array<string, string>}>
     */
    public static function localeProvider(): array
    {
        return [
            'english' => ['en', [
                'expense.pdf_title' => 'Expense receipt',
                'expense.created_by' => 'Recorded by',
                'expense.allocation_amount' => 'Amount',
                'expense.approval_level' => 'Level',
                'expense.approval_by' => 'Approved by',
                'expense.approval_date' => 'Date',
            ]],
            'spanish' => ['es', [
                'expense.pdf_title' => 'Comprobante de gasto',
                'expense.created_by' => 'Registrado por',
                'expense.allocation_amount' => 'Monto',
                'expense.approval_level' => 'Nivel',
                'expense.approval_by' => 'Aprobado por',
                'expense.approval_date' => 'Fecha',
            ]],
        ];
    }

    /**
     * @param array<string, string> $labels
     */
    #[DataProvider('localeProvider')]
    public function testRendersTranslatedLabelsInsteadOfRawKeys(string $locale, array $labels): void
    {
        $content = $this->render($this->buildExpense(true), $locale);

        foreach ($labels as $key => $label) {
            self::assertStringContainsString($label, $content, \sprintf('Expected "%s" for %s in %s.', $label, $key, $locale));
            self::assertStringNotContainsString($key, $content, \sprintf('Raw key %s leaked in %s.', $key, $locale));
        }

        self::assertStringContainsString('Ana Author', $content);
        self::assertStringContainsString('Platform Project', $content);
        self::assertStringContainsString('Bruno Approver', $content);
    }

    public function testOmitsAllocationAndApprovalSectionsWithoutDetails(): void
    {
        $content = $this->render($this->buildExpense(false), 'en');

        self::assertStringContainsString('Expense receipt', $content);
        self::assertStringContainsString('Office rent', $content);
        self::assertStringNotContainsString('Recorded by', $content);
        self::assertStringNotContainsString('Approved by', $content);
        self::assertStringNotContainsString('Platform Project', $content);
    }
}
