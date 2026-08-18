<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Entity;

use App\Entity\Customer;
use App\Entity\Invoice;
use App\Entity\Quotation;
use App\Entity\QuotationLine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

#[CoversClass(Quotation::class)]
class QuotationTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $sut = new Quotation();

        self::assertNull($sut->getId());
        self::assertNull($sut->getCustomer());
        self::assertNull($sut->getProject());
        self::assertNull($sut->getCreatedBy());
        self::assertSame(Quotation::STATUS_DRAFT, $sut->getStatus());
        self::assertNull($sut->getValidUntil());
        self::assertNull($sut->getTax());
        self::assertNull($sut->getDiscount());
        self::assertNull($sut->getSurcharge());
        self::assertNull($sut->getNotes());
        self::assertSame(Quotation::CURRENCY_CLP, $sut->getCurrency());
        self::assertNull($sut->getInvoice());
        self::assertCount(0, $sut->getLines());
        self::assertNotNull($sut->getCreatedAt());
        self::assertNotNull($sut->getModifiedAt());
    }

    public function testCustomerIsRequiredAndLineIsManaged(): void
    {
        $sut = new Quotation();
        $customer = new Customer('Quotation test customer');
        $line = (new QuotationLine())
            ->setDescription('Free text service')
            ->setUnitPrice('100.0000')
            ->setQuantity('2.0000');

        $sut->setCustomer($customer)->addLine($line);

        self::assertSame($customer, $sut->getCustomer());
        self::assertSame($sut, $line->getQuotation());
        self::assertCount(1, $sut->getLines());

        $sut->removeLine($line);
        self::assertNull($line->getQuotation());
        self::assertCount(0, $sut->getLines());
    }

    public function testAtLeastOneLineIsRequired(): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        self::assertNotEmpty($validator->validate(new Quotation()));
    }

    public function testNoMoreThanTenLinesAreAllowed(): void
    {
        $quotation = new Quotation();
        for ($index = 0; $index < 11; ++$index) {
            $quotation->addLine(new QuotationLine());
        }

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        self::assertNotEmpty($validator->validate($quotation));
    }

    public function testOptionalCommercialFieldsAndStatuses(): void
    {
        $sut = new Quotation();

        $sut->setTax('19.0000')->setDiscount('10.0000')->setSurcharge('5.0000');
        $sut->markAsSent();

        self::assertSame('19.0000', $sut->getTax());
        self::assertSame('10.0000', $sut->getDiscount());
        self::assertSame('5.0000', $sut->getSurcharge());
        self::assertSame(Quotation::STATUS_SENT, $sut->getStatus());
    }

    public function testNotesCanBeSetAndCleared(): void
    {
        $sut = new Quotation();

        $sut->setNotes('Precios válidos por 30 días.');
        self::assertSame('Precios válidos por 30 días.', $sut->getNotes());

        $sut->setNotes(null);
        self::assertNull($sut->getNotes());
    }

    public function testCurrencyCanBeSetToAnAllowedValue(): void
    {
        $sut = new Quotation();

        $sut->setCurrency(Quotation::CURRENCY_USD);
        self::assertSame(Quotation::CURRENCY_USD, $sut->getCurrency());

        $sut->setCurrency(Quotation::CURRENCY_UF);
        self::assertSame('CLF', $sut->getCurrency());
    }

    public function testSettingAnUnknownCurrencyIsRejected(): void
    {
        $sut = new Quotation();

        $this->expectException(\InvalidArgumentException::class);
        $sut->setCurrency('BTC');
    }

    public function testValidatorRejectsAnUnknownCurrency(): void
    {
        $sut = new Quotation();
        $sut->setCustomer(new Customer('Currency validation customer'));
        $sut->addLine((new QuotationLine())->setDescription('Item')->setQuantity('1')->setUnitPrice('1'));

        $reflection = new \ReflectionProperty(Quotation::class, 'currency');
        $reflection->setValue($sut, 'BTC');

        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();

        self::assertNotEmpty($validator->validate($sut));
    }

    public function testTransitionsDoNotAllowDirectTerminalEscalation(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only sent quotations can be accepted.');

        (new Quotation())->accept();
    }

    public function testAcceptedQuotationCannotBeRejectedOrResent(): void
    {
        $sut = new Quotation();
        $sut->markAsSent()->accept();

        $this->expectException(\DomainException::class);
        $sut->reject();
    }

    public function testAcceptedQuotationCanBeInvoicedOnlyOnce(): void
    {
        $sut = (new Quotation())->markAsSent()->accept();
        $invoice = new Invoice();

        $sut->markAsInvoiced($invoice);

        self::assertSame(Quotation::STATUS_INVOICED, $sut->getStatus());
        self::assertSame($invoice, $sut->getInvoice());

        $this->expectException(\DomainException::class);
        $sut->markAsInvoiced(new Invoice());
    }

    public function testNonAcceptedQuotationCannotBeInvoiced(): void
    {
        $this->expectException(\DomainException::class);

        (new Quotation())->markAsInvoiced(new Invoice());
    }
}
