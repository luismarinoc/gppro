<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Invoice;

use App\Configuration\LocaleService;
use App\Entity\Customer;
use App\Entity\InvoiceTemplate;
use App\Entity\Milestone;
use App\Entity\Project;
use App\FxRate\ClpConversion;
use App\Invoice\Calculator\DefaultCalculator;
use App\Invoice\InvoiceService;
use App\Invoice\MilestoneInvoiceItem;
use App\Invoice\MilestoneInvoiceService;
use App\Invoice\NumberGenerator\DateNumberGenerator;
use App\Repository\InvoiceDocumentRepository;
use App\Repository\InvoiceRepository;
use App\Repository\MilestoneInvoiceItemRepository;
use App\Repository\Query\MilestoneInvoiceQuery;
use App\Tests\Mocks\InvoiceModelFactoryFactory;
use App\Utils\FileHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[CoversClass(MilestoneInvoiceService::class)]
class MilestoneInvoiceServiceTest extends TestCase
{
    private function createInvoiceService(): InvoiceService
    {
        $languages = ['en' => LocaleService::DEFAULT_SETTINGS];
        $formattings = new LocaleService($languages);
        $repo = new InvoiceDocumentRepository([]);
        $invoiceRepo = $this->createMock(InvoiceRepository::class);

        $invoiceService = new InvoiceService(
            $repo,
            new FileHelper(realpath(__DIR__ . '/../../var/data/')),
            $invoiceRepo,
            $formattings,
            (new InvoiceModelFactoryFactory($this))->create(),
            $this->createMock(EventDispatcherInterface::class)
        );

        $invoiceService->addCalculator(new DefaultCalculator());
        $invoiceService->addNumberGenerator(new DateNumberGenerator($invoiceRepo));

        // Deliberately NOT registered via addInvoiceItemRepository(): proves
        // MilestoneInvoiceService never touches InvoiceService::getInvoiceItems()
        // fan-out (design D2) — it is not even wired up in this test.

        return $invoiceService;
    }

    private function createQuery(): MilestoneInvoiceQuery
    {
        $customer = new Customer('Milestone customer');
        $template = new InvoiceTemplate();
        $template->setNumberGenerator('date');
        $template->setLanguage('en');

        $query = new MilestoneInvoiceQuery();
        $query->setCustomers([$customer]);
        $query->setTemplate($template);

        return $query;
    }

    public function testCreateModelAssemblesModelViaCreateModelWithoutEntriesAndAddsRepositoryEntries(): void
    {
        $project = new Project();
        $project->setCustomer(new Customer('Milestone customer'));
        $milestone = new Milestone();
        $milestone->setProject($project);
        $milestone->setName('Design phase');
        $milestone->setValue('1500.0000');
        $milestone->setCurrency('USD');

        $item = new MilestoneInvoiceItem($milestone, ClpConversion::identity('1500.0000'));

        $repository = $this->createMock(MilestoneInvoiceItemRepository::class);
        $repository->expects($this->once())
            ->method('getInvoiceItemsForQuery')
            ->willReturn([$item]);

        $sut = new MilestoneInvoiceService($this->createInvoiceService(), $repository);

        $model = $sut->createModel($this->createQuery());

        self::assertCount(1, $model->getEntries());
        self::assertSame($item, $model->getEntries()[0]);
    }

    public function testCreateModelWithNoInvoiceableMilestonesProducesAnEmptyModel(): void
    {
        $repository = $this->createMock(MilestoneInvoiceItemRepository::class);
        $repository->expects($this->once())
            ->method('getInvoiceItemsForQuery')
            ->willReturn([]);

        $sut = new MilestoneInvoiceService($this->createInvoiceService(), $repository);

        $model = $sut->createModel($this->createQuery());

        self::assertCount(0, $model->getEntries());
    }
}
