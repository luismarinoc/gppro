<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Repository;

use App\Entity\Customer;
use App\Entity\InvoiceTemplate;
use App\Repository\InvoiceTemplateRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(InvoiceTemplateRepository::class)]
#[Group('integration')]
class InvoiceTemplateRepositoryTest extends AbstractRepositoryTestCase
{
    private function getRepository(): InvoiceTemplateRepository
    {
        /** @var InvoiceTemplateRepository $repository */
        $repository = $this->getEntityManager()->getRepository(InvoiceTemplate::class);

        return $repository;
    }

    private function createTemplate(string $calculator): InvoiceTemplate
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Invoice template repository test customer ' . uniqid());
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $suffix = uniqid();
        $template = new InvoiceTemplate();
        $template->setName('Template ' . $suffix);
        $template->setTitle('Title ' . $suffix);
        $template->setCustomer($customer);
        $template->setCalculator($calculator);
        $template->setRenderer('default');
        $em->persist($template);
        $em->flush();

        return $template;
    }

    public function testMilestoneFormTypeQueryBuilderOnlyReturnsCompatibleCalculators(): void
    {
        $repository = $this->getRepository();

        $compatible = $this->createTemplate('default');
        $incompatibleUser = $this->createTemplate('user');
        $incompatibleActivityUser = $this->createTemplate('activity_user');
        $incompatibleProjectUser = $this->createTemplate('project_user');
        $incompatibleDateUser = $this->createTemplate('date_user');

        $result = $repository->getQueryBuilderForMilestoneFormType()->getQuery()->getResult();
        $resultIds = array_map(static fn (InvoiceTemplate $t): ?int => $t->getId(), $result);

        self::assertContains($compatible->getId(), $resultIds);
        self::assertNotContains($incompatibleUser->getId(), $resultIds);
        self::assertNotContains($incompatibleActivityUser->getId(), $resultIds);
        self::assertNotContains($incompatibleProjectUser->getId(), $resultIds);
        self::assertNotContains($incompatibleDateUser->getId(), $resultIds);
    }

    public function testMilestoneFormTypeQueryBuilderIncludesAllEightCompatibleCalculators(): void
    {
        $repository = $this->getRepository();

        $ids = [];
        foreach (['short', 'price', 'date', 'weekly', 'activity', 'project', 'project_activity'] as $calculator) {
            $ids[] = $this->createTemplate($calculator)->getId();
        }

        $result = $repository->getQueryBuilderForMilestoneFormType()->getQuery()->getResult();
        $resultIds = array_map(static fn (InvoiceTemplate $t): ?int => $t->getId(), $result);

        foreach ($ids as $id) {
            self::assertContains($id, $resultIds);
        }
    }
}
