<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Invoice\Calculator;

use App\Configuration\LocaleService;
use App\Entity\Customer;
use App\Entity\ExportableItem;
use App\Entity\InvoiceTemplate;
use App\Entity\MetaTableTypeInterface;
use App\Entity\Milestone;
use App\Entity\Project;
use App\FxRate\ClpConversion;
use App\Invoice\Calculator\ActivityInvoiceCalculator;
use App\Invoice\Calculator\ActivityUserInvoiceCalculator;
use App\Invoice\Calculator\DateInvoiceCalculator;
use App\Invoice\Calculator\DateUserInvoiceCalculator;
use App\Invoice\Calculator\DefaultCalculator;
use App\Invoice\Calculator\PriceInvoiceCalculator;
use App\Invoice\Calculator\ProjectActivityInvoiceCalculator;
use App\Invoice\Calculator\ProjectInvoiceCalculator;
use App\Invoice\Calculator\ProjectUserInvoiceCalculator;
use App\Invoice\Calculator\ShortInvoiceCalculator;
use App\Invoice\Calculator\UserInvoiceCalculator;
use App\Invoice\Calculator\WeeklyInvoiceCalculator;
use App\Invoice\CalculatorInterface;
use App\Invoice\InvoiceItemRepositoryInterface;
use App\Invoice\InvoiceModel;
use App\Invoice\InvoiceService;
use App\Invoice\MilestoneInvoiceItem;
use App\Invoice\NumberGenerator\DateNumberGenerator;
use App\Repository\InvoiceDocumentRepository;
use App\Repository\InvoiceRepository;
use App\Repository\Query\InvoiceQuery;
use App\Tests\Invoice\DebugFormatter;
use App\Tests\Mocks\InvoiceModelFactoryFactory;
use App\Utils\FileHelper;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * R1 regression sweep (design D1): a milestone invoice is assembled from
 * MilestoneInvoiceItem adapters and is fed to the SAME calculator pipeline
 * used for ordinary timesheet invoices. Every shipped calculator must be
 * able to consume it without a fatal.
 *
 * The project actually ships TWELVE concrete CalculatorInterface
 * implementations, not the eleven originally assumed in the design doc
 * (`date_user` — DateUserInvoiceCalculator — was missed there). All twelve
 * are swept here.
 *
 * CRITICAL FINDING (documented, not silently patched — see
 * testCalculatorsThatRequireAPersistedUserRejectMilestoneEntries below):
 * FOUR of those twelve calculators (`user`, `activity_user`, `project_user`,
 * `date_user`) unconditionally throw when an entry's getUser() is null.
 * MilestoneInvoiceItem::getUser() is ALWAYS null (milestones are not tied to
 * a specific user) by design (see design Interfaces/Contracts section). This
 * is a DIFFERENT defect than R1/D1 (which was about begin/end, not user) and
 * is NOT fixed by D1's synthetic begin/end. A milestone invoice generated
 * against an InvoiceTemplate configured with one of those 4 calculators will
 * throw an uncaught \Exception today. This test locks in and documents that
 * exact behavior as a known gap for a future fix (either exclude those
 * calculators from milestone-eligible templates, or give
 * MilestoneInvoiceItem a fallback synthetic user) — it does not attempt to
 * silently paper over it here.
 */
#[CoversClass(MilestoneInvoiceItem::class)]
class MilestoneCalculatorCompatibilityTest extends TestCase
{
    /**
     * The 8 calculators that never inspect getUser() and are fully
     * compatible with MilestoneInvoiceItem after D1 (non-null begin/end).
     */
    public static function compatibleCalculatorProvider(): iterable
    {
        yield 'default' => [new DefaultCalculator()];
        yield 'short' => [new ShortInvoiceCalculator()];
        yield 'price' => [new PriceInvoiceCalculator()];
        yield 'date' => [new DateInvoiceCalculator()];
        yield 'weekly' => [new WeeklyInvoiceCalculator()];
        yield 'activity' => [new ActivityInvoiceCalculator()];
        yield 'project' => [new ProjectInvoiceCalculator()];
        yield 'project_activity' => [new ProjectActivityInvoiceCalculator()];
    }

    /**
     * The 4 calculators that require a persisted (non-null) user and will
     * throw for any MilestoneInvoiceItem regardless of D1 — see the CRITICAL
     * FINDING in the class docblock.
     */
    public static function userBasedCalculatorProvider(): iterable
    {
        yield 'user' => [new UserInvoiceCalculator()];
        yield 'activity_user' => [new ActivityUserInvoiceCalculator()];
        yield 'project_user' => [new ProjectUserInvoiceCalculator()];
        yield 'date_user' => [new DateUserInvoiceCalculator()];
    }

    /**
     * All 12 shipped calculators, used for the null-begin baseline proof.
     */
    public static function allCalculatorProvider(): iterable
    {
        yield from self::compatibleCalculatorProvider();
        yield from self::userBasedCalculatorProvider();
    }

    #[DataProvider('allCalculatorProvider')]
    public function testNullBeginBaselineReproducesTheHistoricalR1Crash(CalculatorInterface $calculator): void
    {
        // This is the pre-D1 baseline: an ExportableItem that returns a null
        // begin (as an incorrectly-implemented adapter would have, before
        // D1 mandated a non-null synthetic begin/end). Every calculator must
        // fail on this input — proving the invariant D1 relies on is real,
        // not incidental.
        $entry = $this->createNullBeginExportableItem();

        $model = $this->buildModel([$entry]);
        $calculator->setModel($model);

        $this->expectException(\Throwable::class);

        $calculator->getEntries();
    }

    #[DataProvider('compatibleCalculatorProvider')]
    public function testCalculatorSurvivesTwoOrMoreMilestoneInvoiceItems(CalculatorInterface $calculator): void
    {
        [$itemA, $itemB] = $this->twoMilestoneInvoiceItems();

        $model = $this->buildModel([$itemA, $itemB]);
        $calculator->setModel($model);

        $entries = $calculator->getEntries();

        self::assertNotEmpty($entries);
        self::assertEqualsWithDelta(1925185.10, $calculator->getSubtotal(), 0.001);
    }

    #[DataProvider('userBasedCalculatorProvider')]
    public function testCalculatorsThatRequireAPersistedUserRejectMilestoneEntries(CalculatorInterface $calculator): void
    {
        [$itemA, $itemB] = $this->twoMilestoneInvoiceItems();

        $model = $this->buildModel([$itemA, $itemB]);
        $calculator->setModel($model);

        // Message wording differs slightly by calculator (some check
        // "un-persisted user", ProjectUserInvoiceCalculator checks "without
        // user" first since it validates project before user) — all are
        // driven by the same root cause: MilestoneInvoiceItem::getUser() is
        // always null.
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/user/i');

        $calculator->getEntries();
    }

    public function testInvoicePeriodUsesTheMinAndMaxMilestoneDueDate(): void
    {
        [$itemA, $itemB] = $this->twoMilestoneInvoiceItems();

        $model = $this->buildModel([$itemA, $itemB]);

        $period = $model->getInvoicePeriod();

        self::assertEquals('2026-07-01', $period->getStart()->format('Y-m-d'));
        self::assertEquals('2026-07-20', $period->getEnd()->format('Y-m-d'));
    }

    public function testPrepareModelQueryDatesSetsNonNullBeginAndEndFromMilestoneEntries(): void
    {
        [$itemA, $itemB] = $this->twoMilestoneInvoiceItems();

        $repository = $this->createMock(InvoiceItemRepositoryInterface::class);
        $repository->method('getInvoiceItemsForQuery')->willReturn([$itemA, $itemB]);

        $template = new InvoiceTemplate();
        $template->setNumberGenerator('date');

        $customer = new Customer('foo');
        $query = new InvoiceQuery();
        $query->setCustomers([$customer]);
        $query->setTemplate($template);

        self::assertNull($query->getBegin());
        self::assertNull($query->getEnd());

        $sut = $this->getInvoiceServiceSut();
        $sut->addCalculator(new DefaultCalculator());
        $sut->addNumberGenerator($this->getNumberGeneratorSut());
        $sut->addInvoiceItemRepository($repository);

        $sut->createModel($query);

        self::assertNotNull($query->getBegin());
        self::assertNotNull($query->getEnd());
        self::assertEquals('2026-07-01', $query->getBegin()->format('Y-m-d'));
        self::assertEquals('2026-07-20', $query->getEnd()->format('Y-m-d'));
    }

    /**
     * @return array{0: MilestoneInvoiceItem, 1: MilestoneInvoiceItem}
     */
    private function twoMilestoneInvoiceItems(): array
    {
        $milestoneA = new Milestone();
        $milestoneA->setProject($this->mockProject(1));
        $milestoneA->setName('Design phase');
        $milestoneA->setValue('1500.0000');
        $milestoneA->setCurrency('USD');
        $milestoneA->setDueDate(new \DateTime('2026-07-01'));

        $milestoneB = new Milestone();
        $milestoneB->setProject($this->mockProject(2));
        $milestoneB->setName('Delivery phase');
        $milestoneB->setValue('500000.0000');
        $milestoneB->setCurrency('CLP');
        $milestoneB->setDueDate(new \DateTime('2026-07-20'));

        $itemA = new MilestoneInvoiceItem(
            $milestoneA,
            ClpConversion::converted('1500.0000', 'USD', '950.1234', new \DateTimeImmutable('2026-07-01'), '1425185.1000')
        );
        $itemB = new MilestoneInvoiceItem($milestoneB, ClpConversion::identity('500000.0000'));

        return [$itemA, $itemB];
    }

    private function mockProject(int $id): Project
    {
        $project = $this->getMockBuilder(Project::class)->onlyMethods(['getId'])->disableOriginalConstructor()->getMock();
        $project->method('getId')->willReturn($id);

        return $project;
    }

    private function createNullBeginExportableItem(): ExportableItem
    {
        return new class() implements ExportableItem {
            public function getId(): int
            {
                return 1;
            }

            public function isExported(): bool
            {
                return false;
            }

            public function isBillable(): bool
            {
                return true;
            }

            public function getMetaField(string $name): ?MetaTableTypeInterface
            {
                return null;
            }

            public function getTagsAsArray(): array
            {
                return [];
            }

            public function getAmount(): float
            {
                return 1.0;
            }

            public function getActivity(): ?\App\Entity\Activity
            {
                return null;
            }

            public function getProject(): ?Project
            {
                return null;
            }

            public function getFixedRate(): float
            {
                return 100.0;
            }

            public function getHourlyRate(): ?float
            {
                return null;
            }

            public function getRate(): float
            {
                return 100.0;
            }

            public function getInternalRate(): ?float
            {
                return null;
            }

            public function getUser(): ?\App\Entity\User
            {
                return null;
            }

            public function getBegin(): ?\DateTime
            {
                return null;
            }

            public function getEnd(): ?\DateTime
            {
                return null;
            }

            public function getDuration(): ?int
            {
                return null;
            }

            public function getDescription(): string
            {
                return 'null-begin baseline';
            }

            public function getVisibleMetaFields(): array
            {
                return [];
            }

            /**
             * @return Collection<int, MetaTableTypeInterface>
             */
            public function getMetaFields(): Collection
            {
                return new ArrayCollection();
            }

            public function getType(): string
            {
                return 'baseline';
            }

            public function getCategory(): string
            {
                return 'baseline';
            }
        };
    }

    /**
     * @param ExportableItem[] $entries
     */
    private function buildModel(array $entries): InvoiceModel
    {
        $customer = new Customer('foo');
        $template = new InvoiceTemplate();
        $query = new InvoiceQuery();

        $model = (new InvoiceModelFactoryFactory($this))->create()->createModel(new DebugFormatter(), $customer, $template, $query);
        $model->addEntries($entries);

        return $model;
    }

    private function getInvoiceServiceSut(): InvoiceService
    {
        $languages = [
            'en' => LocaleService::DEFAULT_SETTINGS,
        ];

        $formattings = new LocaleService($languages);

        $repo = new InvoiceDocumentRepository([]);
        $invoiceRepo = $this->createMock(InvoiceRepository::class);

        return new InvoiceService(
            $repo,
            new FileHelper(realpath(__DIR__ . '/../../../var/data/')),
            $invoiceRepo,
            $formattings,
            (new InvoiceModelFactoryFactory($this))->create(),
            $this->createMock(EventDispatcherInterface::class)
        );
    }

    private function getNumberGeneratorSut(): DateNumberGenerator
    {
        $repository = $this->createMock(InvoiceRepository::class);
        $repository
            ->expects($this->any())
            ->method('hasInvoice')
            ->willReturn(false);

        return new DateNumberGenerator($repository);
    }
}
