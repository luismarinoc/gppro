<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Command;

use App\Command\FxRatesSyncCommand;
use App\Entity\FxRate;
use App\FxRate\FxRateSynchronizer;
use App\FxRate\MindicadorClient;
use App\FxRate\MindicadorUnavailableException;
use App\Repository\FxRateRepository;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(FxRatesSyncCommand::class)]
#[Group('integration')]
class FxRatesSyncCommandTest extends KernelTestCase
{
    private Application $application;
    private ObjectManager $entityManager;
    private FxRateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $kernel = self::bootKernel();

        /** @var Registry $doctrine */
        $doctrine = $kernel->getContainer()->get('doctrine');
        $this->entityManager = $doctrine->getManager();

        /** @var FxRateRepository $repository */
        $repository = $this->entityManager->getRepository(FxRate::class);
        $this->repository = $repository;

        $this->application = new Application($kernel);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->entityManager instanceof \Doctrine\ORM\EntityManagerInterface) {
            $this->entityManager->close();
        }
    }

    private function addCommand(MindicadorClient $client, ?MockClock $clock = null): Command
    {
        $synchronizer = new FxRateSynchronizer($client, $this->repository);
        $command = new FxRatesSyncCommand($synchronizer, new NullLogger(), $clock ?? new MockClock());
        $this->application->add($command);

        $found = $this->application->find('gppro:fx-rates:sync');
        self::assertInstanceOf(FxRatesSyncCommand::class, $found);

        return $found;
    }

    private function mindicadorReturning(string $usdValue, string $ufValue): MindicadorClient
    {
        $client = $this->createMock(MindicadorClient::class);
        $client->method('fetchValue')->willReturnCallback(
            static fn (string $indicator): string => FxRate::INDICATOR_USD === $indicator ? $usdValue : $ufValue
        );

        return $client;
    }

    public function testCommandName(): void
    {
        $command = $this->addCommand($this->createMock(MindicadorClient::class));

        self::assertSame('gppro:fx-rates:sync', $command->getName());
    }

    public function testSuccessfulRunWithDataPersistsBothIndicatorsAndExitsSuccess(): void
    {
        $client = $this->mindicadorReturning('950.500000', '39100.123456');
        $command = $this->addCommand($client);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['command' => $command->getName(), '--date' => '2026-07-10']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $date = new \DateTimeImmutable('2026-07-10');
        $usd = $this->repository->findOneByDateAndIndicator($date, FxRate::INDICATOR_USD);
        $uf = $this->repository->findOneByDateAndIndicator($date, FxRate::INDICATOR_UF);

        self::assertNotNull($usd);
        self::assertSame('950.500000', $usd->getRateValue());
        self::assertNotNull($uf);
        self::assertSame('39100.123456', $uf->getRateValue());
    }

    public function testSuccessfulRunWithoutDataPersistsNothingAndExitsSuccess(): void
    {
        $client = $this->createMock(MindicadorClient::class);
        $client->method('fetchValue')->willReturn(null);
        $command = $this->addCommand($client);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['command' => $command->getName(), '--date' => '2026-07-11']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $date = new \DateTimeImmutable('2026-07-11');
        self::assertNull($this->repository->findOneByDateAndIndicator($date, FxRate::INDICATOR_USD));
        self::assertNull($this->repository->findOneByDateAndIndicator($date, FxRate::INDICATOR_UF));
    }

    public function testTransportFailureOnOneIndicatorExitsFailureButPersistsTheOtherIndicator(): void
    {
        $client = $this->createMock(MindicadorClient::class);
        $client->method('fetchValue')->willReturnCallback(
            static function (string $indicator): string {
                if (FxRate::INDICATOR_USD === $indicator) {
                    throw new MindicadorUnavailableException('simulated transport failure');
                }

                return '39200.000000';
            }
        );
        $command = $this->addCommand($client);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['command' => $command->getName(), '--date' => '2026-07-12']);

        self::assertSame(Command::FAILURE, $exitCode);

        $date = new \DateTimeImmutable('2026-07-12');
        self::assertNull($this->repository->findOneByDateAndIndicator($date, FxRate::INDICATOR_USD));
        self::assertNotNull($this->repository->findOneByDateAndIndicator($date, FxRate::INDICATOR_UF));
    }

    public function testInvalidDateFormatExitsInvalidAndPersistsNothing(): void
    {
        $command = $this->addCommand($this->createMock(MindicadorClient::class));

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['command' => $command->getName(), '--date' => '10-07-2026']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('Invalid --date', $tester->getDisplay());
    }

    public function testFutureDateExitsInvalid(): void
    {
        $command = $this->addCommand($this->createMock(MindicadorClient::class), new MockClock('2026-07-10 12:00:00', 'UTC'));

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['command' => $command->getName(), '--date' => '2026-07-20']);

        self::assertSame(2, $exitCode);
        self::assertStringContainsString('future', $tester->getDisplay());
    }

    public function testForceOverwritesExistingRow(): void
    {
        $date = new \DateTimeImmutable('2026-07-13');
        $this->repository->upsert($date, FxRate::INDICATOR_USD, '900.000000', false);
        $this->repository->upsert($date, FxRate::INDICATOR_UF, '38000.000000', false);

        $client = $this->mindicadorReturning('999.000000', '39999.000000');
        $command = $this->addCommand($client);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'command' => $command->getName(),
            '--date' => '2026-07-13',
            '--force' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $usd = $this->repository->findOneByDateAndIndicator($date, FxRate::INDICATOR_USD);
        self::assertNotNull($usd);
        self::assertSame('999.000000', $usd->getRateValue());
    }

    public function testWithoutForceExistingRowIsLeftUnchanged(): void
    {
        $date = new \DateTimeImmutable('2026-07-14');
        $this->repository->upsert($date, FxRate::INDICATOR_USD, '900.000000', false);
        $this->repository->upsert($date, FxRate::INDICATOR_UF, '38000.000000', false);

        $client = $this->mindicadorReturning('999.000000', '39999.000000');
        $command = $this->addCommand($client);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['command' => $command->getName(), '--date' => '2026-07-14']);

        self::assertSame(Command::SUCCESS, $exitCode);

        $usd = $this->repository->findOneByDateAndIndicator($date, FxRate::INDICATOR_USD);
        self::assertNotNull($usd);
        self::assertSame('900.000000', $usd->getRateValue());
    }

    public function testDefaultDateResolvesTodayInSantiagoNotHostTimezone(): void
    {
        // 2026-07-16 02:30:00 UTC is already 2026-07-15 23:30:00 in Berlin (CEST, UTC+2,
        // so this instant does NOT cross midnight there) but only 2026-07-15 22:30:00 in
        // Santiago (UTC-4, Chilean winter) — same calendar day both places, so pick an
        // instant that actually differs: 2026-07-16 01:30:00 UTC is 2026-07-16 03:30:00
        // in Berlin (already the 16th) but 2026-07-15 21:30:00 in Santiago (still the 15th).
        $clock = new MockClock('2026-07-16 01:30:00', 'UTC');
        $client = $this->mindicadorReturning('950.000000', '39000.000000');
        $command = $this->addCommand($client, $clock);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['command' => $command->getName()]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $santiagoToday = new \DateTimeImmutable('2026-07-15');
        $berlinToday = new \DateTimeImmutable('2026-07-16');

        self::assertNotNull($this->repository->findOneByDateAndIndicator($santiagoToday, FxRate::INDICATOR_USD));
        self::assertNull($this->repository->findOneByDateAndIndicator($berlinToday, FxRate::INDICATOR_USD));
    }
}
