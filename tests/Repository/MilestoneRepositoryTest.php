<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Repository;

use App\Entity\Customer;
use App\Entity\Milestone;
use App\Entity\Project;
use App\Repository\MilestoneRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(MilestoneRepository::class)]
#[Group('integration')]
class MilestoneRepositoryTest extends AbstractRepositoryTestCase
{
    private function getRepository(): MilestoneRepository
    {
        /** @var MilestoneRepository $repository */
        $repository = $this->getEntityManager()->getRepository(Milestone::class);

        return $repository;
    }

    private function createProject(): Project
    {
        $em = $this->getEntityManager();

        $customer = new Customer('Milestone repository test customer');
        $customer->setCountry('CL');
        $customer->setTimezone('America/Santiago');
        $em->persist($customer);

        $project = new Project();
        $project->setName('Milestone repository test project');
        $project->setCustomer($customer);
        $em->persist($project);

        $em->flush();

        return $project;
    }

    public function testValueAndCurrencyRoundTripWithoutPrecisionLoss(): void
    {
        $em = $this->getEntityManager();
        $repository = $this->getRepository();
        $project = $this->createProject();

        $milestone = new Milestone();
        $milestone->setProject($project);
        $milestone->setName('Milestone with value');
        $milestone->setValue('5000.1234');
        $milestone->setCurrency('USD');
        $repository->saveMilestone($milestone);

        $id = $milestone->getId();
        $em->clear();

        $stored = $repository->find($id);

        self::assertNotNull($stored);
        self::assertSame('5000.1234', $stored->getValue());
        self::assertSame('USD', $stored->getCurrency());
    }

    public function testValueAndCurrencyPersistAsNullByDefault(): void
    {
        $em = $this->getEntityManager();
        $repository = $this->getRepository();
        $project = $this->createProject();

        $milestone = new Milestone();
        $milestone->setProject($project);
        $milestone->setName('Milestone without value');
        $repository->saveMilestone($milestone);

        $id = $milestone->getId();
        $em->clear();

        $stored = $repository->find($id);

        self::assertNotNull($stored);
        self::assertNull($stored->getValue());
        self::assertNull($stored->getCurrency());
    }
}
