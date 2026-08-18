<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Service;

use App\Entity\Expense;
use App\Pdf\HtmlToPdfConverter;
use App\Service\ExpensePdfRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

#[CoversClass(ExpensePdfRenderer::class)]
class ExpensePdfRendererTest extends TestCase
{
    public function testRendersExpenseData(): void
    {
        $expense = (new Expense())->setDescription('Office rent')->setAmount(150000)->setCurrency('CLP')->setExpenseDate(new \DateTimeImmutable('2026-08-01'));
        $id = new \ReflectionProperty(Expense::class, 'id');
        $id->setValue($expense, 9);

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())->method('render')->with('expense/pdf.html.twig', self::callback(static function (array $context) use ($expense): bool {
            return ($context['expense'] ?? null) === $expense && \array_key_exists('defaultLogo', $context);
        }))->willReturn('<html></html>');
        $converter = $this->createMock(HtmlToPdfConverter::class);
        $converter->expects(self::once())->method('convertToPdf')->with('<html></html>', self::arrayHasKey('filename'))->willReturn('%PDF');

        self::assertSame('%PDF', (new ExpensePdfRenderer($twig, $converter, \dirname(__DIR__, 2)))->render($expense));
    }

    public function testThrowsWhenExpenseHasNoId(): void
    {
        $expense = (new Expense())->setDescription('Unsaved')->setAmount(1)->setCurrency('CLP')->setExpenseDate(new \DateTimeImmutable());

        $twig = $this->createMock(Environment::class);
        $twig->expects(self::never())->method('render');
        $converter = $this->createMock(HtmlToPdfConverter::class);
        $converter->expects(self::never())->method('convertToPdf');

        $this->expectException(\DomainException::class);

        (new ExpensePdfRenderer($twig, $converter, \dirname(__DIR__, 2)))->render($expense);
    }
}
