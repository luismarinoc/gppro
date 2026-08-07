<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Repository;

use App\Entity\Customer;
use App\Entity\Quotation;
use App\Entity\QuotationCatalogItem;
use App\Entity\QuotationEmail;
use App\Entity\QuotationLine;
use App\Repository\QuotationCatalogItemRepository;
use App\Repository\QuotationEmailRepository;
use App\Repository\QuotationRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(QuotationRepository::class)]
#[CoversClass(QuotationCatalogItemRepository::class)]
#[CoversClass(QuotationEmailRepository::class)]
#[Group('integration')]
class QuotationRepositoryTest extends AbstractRepositoryTestCase
{
    private function createCustomer(string $name): Customer
    {
        $customer = new Customer($name . ' ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $this->getEntityManager()->persist($customer);
        $this->getEntityManager()->flush();

        return $customer;
    }

    private function getQuotationRepository(): QuotationRepository
    {
        /** @var QuotationRepository $repository */
        $repository = $this->getEntityManager()->getRepository(Quotation::class);

        return $repository;
    }

    private function getCatalogRepository(): QuotationCatalogItemRepository
    {
        /** @var QuotationCatalogItemRepository $repository */
        $repository = $this->getEntityManager()->getRepository(QuotationCatalogItem::class);

        return $repository;
    }

    public function testFindValidQuotationEmailRejectsExpiredAndRespondedTokens(): void
    {
        /** @var QuotationEmailRepository $repository */
        $repository = $this->getEntityManager()->getRepository(QuotationEmail::class);
        $customer = $this->createCustomer('Email customer');
        $quotation = (new Quotation())->setCustomer($customer)->markAsSent();
        $valid = new QuotationEmail($quotation, 'client@example.com', str_repeat('a', 64), new \DateTimeImmutable('+1 day'), new \DateTimeImmutable());
        $expired = new QuotationEmail($quotation, 'client@example.com', str_repeat('b', 64), new \DateTimeImmutable('-1 day'), new \DateTimeImmutable());
        $responded = new QuotationEmail($quotation, 'client@example.com', str_repeat('c', 64), new \DateTimeImmutable('+1 day'), new \DateTimeImmutable());
        $responded->recordResponse('accepted', new \DateTimeImmutable(), '192.0.2.1', 'test');
        $em = $this->getEntityManager();
        $em->persist($quotation);
        $em->persist($valid);
        $em->persist($expired);
        $em->persist($responded);
        $em->flush();

        $found = $repository->findValidToken(str_repeat('a', 64), new \DateTimeImmutable());
        self::assertNotNull($found);
        self::assertSame($valid->getId(), $found->getId());
        self::assertNull($repository->findValidToken(str_repeat('b', 64), new \DateTimeImmutable()));
        self::assertNull($repository->findValidToken(str_repeat('c', 64), new \DateTimeImmutable()));
    }

    public function testFindActiveCatalogItemsExcludesInactiveItems(): void
    {
        $repository = $this->getCatalogRepository();
        $active = (new QuotationCatalogItem())->setName('Active ' . uniqid())->setUnit('item')->setDefaultPrice('10.0000');
        $inactive = (new QuotationCatalogItem())->setName('Inactive ' . uniqid())->setUnit('item')->setDefaultPrice('20.0000')->setActive(false);
        $repository->saveCatalogItem($active);
        $repository->saveCatalogItem($inactive);

        $ids = array_map(static fn (QuotationCatalogItem $item): ?int => $item->getId(), $repository->findActive());

        self::assertContains($active->getId(), $ids);
        self::assertNotContains($inactive->getId(), $ids);
    }

    public function testFindByCustomerReturnsOnlyThatCustomersQuotationsAndPersistsSnapshots(): void
    {
        $repository = $this->getQuotationRepository();
        $customer = $this->createCustomer('Quotation customer');
        $otherCustomer = $this->createCustomer('Other quotation customer');
        $quotation = (new Quotation())->setCustomer($customer);
        $quotation->addLine((new QuotationLine())->setDescription('Snapshot')->setUnit('hour')->setUnitPrice('125.5000')->setQuantity('2.0000'));
        $otherQuotation = (new Quotation())->setCustomer($otherCustomer);
        $repository->saveQuotation($quotation);
        $repository->saveQuotation($otherQuotation);

        $this->getEntityManager()->clear();
        $results = $repository->findByCustomer($customer);

        self::assertCount(1, $results);
        self::assertSame($quotation->getId(), $results[0]->getId());
        self::assertCount(1, $results[0]->getLines());
        $line = $results[0]->getLines()->first();
        self::assertInstanceOf(QuotationLine::class, $line);
        self::assertSame('125.5000', $line->getUnitPrice());
    }
}
