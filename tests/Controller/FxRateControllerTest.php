<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Controller;

use App\Entity\FxRate;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * PR4 covered permission boundaries only (403/redirect cases); the
 * fx_rates/*.html.twig templates added in PR5 make the success-path (200)
 * flows below possible: list rendering, create, edit, and delete.
 */
#[Group('integration')]
class FxRateControllerTest extends AbstractControllerBaseTestCase
{
    private function importFxRate(): FxRate
    {
        $fxRate = new FxRate();
        $fxRate->setDate(new \DateTimeImmutable('2026-07-20'));
        $fxRate->setIndicator(FxRate::INDICATOR_USD);
        $fxRate->setRateValue('933.920000');

        $em = $this->getEntityManager();
        $em->persist($fxRate);
        $em->flush();

        return $fxRate;
    }

    /**
     * @return FxRate[]
     */
    private function importFxRates(): array
    {
        $em = $this->getEntityManager();

        $usd = new FxRate();
        $usd->setDate(new \DateTimeImmutable('2026-07-20'));
        $usd->setIndicator(FxRate::INDICATOR_USD);
        $usd->setRateValue('933.920000');
        $em->persist($usd);

        $uf = new FxRate();
        $uf->setDate(new \DateTimeImmutable('2026-07-20'));
        $uf->setIndicator(FxRate::INDICATOR_UF);
        $uf->setRateValue('39123.450000');
        $em->persist($uf);

        $em->flush();

        return [$usd, $uf];
    }

    public function testIsSecure(): void
    {
        $this->assertUrlIsSecured('/admin/fx-rates/');
    }

    public function testIndexActionDeniedWithoutViewPermission(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_TEAMLEAD, '/admin/fx-rates/');
    }

    public function testCreateActionDeniedWithoutCreatePermission(): void
    {
        $this->assertUrlIsSecuredForRole(User::ROLE_TEAMLEAD, '/admin/fx-rates/create');
    }

    public function testEditActionDeniedWithoutManagePermission(): void
    {
        // A single client must be created first (WebTestCase forbids booting the
        // kernel twice), then reused both to persist the fixture and to request
        // the protected URL - mirrors assertUrlIsSecuredForRole() without a second
        // internal createClient() call.
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $fxRate = $this->importFxRate();

        $this->request($client, '/admin/fx-rates/' . $fxRate->getId() . '/edit');
        self::assertFalse($client->getResponse()->isSuccessful());
        $this->assertAccessDenied($client);
    }

    public function testDeleteActionDeniedWithoutDeletePermission(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_TEAMLEAD);
        $fxRate = $this->importFxRate();

        $this->request($client, '/admin/fx-rates/' . $fxRate->getId() . '/delete');
        self::assertFalse($client->getResponse()->isSuccessful());
        $this->assertAccessDenied($client);
    }

    public function testIndexActionIsGrantedForAdmin(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->importFxRate();

        // The class-level #[IsGranted('view_fx_rate')] MUST let ROLE_ADMIN through
        // before the controller body runs. As of PR5, fx_rates/index.html.twig
        // exists, so this is now a real 200, not just "not 403".
        $this->assertAccessIsGranted($client, '/admin/fx-rates/');
    }

    public function testIndexActionRendersDataTable(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->importFxRates();

        $this->assertAccessIsGranted($client, '/admin/fx-rates/');
        $this->assertHasDataTable($client);
        $this->assertDataTableRowCount($client, 'datatable_admin_fx_rates', 2);
    }

    public function testCreateAction(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $this->assertAccessIsGranted($client, '/admin/fx-rates/create');

        $form = $client->getCrawler()->filter('form[name=fx_rate_edit_form]')->form();
        $client->submit($form, [
            'fx_rate_edit_form' => [
                // DatePickerType renders 'single_text' with the locale's date
                // format (M/d/y for the default test locale), not ISO.
                'date' => '06/01/2026',
                'indicator' => FxRate::INDICATOR_USD,
                'rateValue' => '912.345600',
            ],
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/fx-rates/'));
        $client->followRedirect();
        $this->assertHasDataTable($client);
        $this->assertHasFlashSuccess($client);

        $em = $this->getEntityManager();
        $em->clear();
        /** @var FxRate $created */
        $created = $em->getRepository(FxRate::class)->findAll()[0];
        self::assertSame(FxRate::INDICATOR_USD, $created->getIndicator());
        self::assertSame('912.345600', $created->getRateValue());
        self::assertSame('2026-06-01', $created->getDate()?->format('Y-m-d'));
        self::assertNotNull($created->getModifiedAt());
    }

    public function testEditActionUpdatesValueAndModifiedAt(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        $fxRate = $this->importFxRate();
        $id = $fxRate->getId();

        $this->assertAccessIsGranted($client, '/admin/fx-rates/' . $id . '/edit');
        $form = $client->getCrawler()->filter('form[name=fx_rate_edit_form]')->form();
        $client->submit($form, [
            'fx_rate_edit_form' => [
                'date' => '07/20/2026',
                'indicator' => FxRate::INDICATOR_USD,
                'rateValue' => '999.990000',
            ],
        ]);
        $this->assertIsRedirect($client, $this->createUrl('/admin/fx-rates/'));
        $client->followRedirect();
        $this->assertHasDataTable($client);

        $em = $this->getEntityManager();
        $em->clear();
        /** @var FxRate $updated */
        $updated = $em->getRepository(FxRate::class)->find($id);
        self::assertSame('999.990000', $updated->getRateValue());
        self::assertNotNull($updated->getModifiedAt());
    }

    public function testDeleteActionRemovesRow(): void
    {
        $client = $this->getClientForAuthenticatedUser(User::ROLE_ADMIN);
        // Import two rows: after deleting one, the other keeps the list
        // non-empty so the redirect target renders the DataTable, not the
        // "no entries found" empty state.
        [$toDelete, $remaining] = $this->importFxRates();
        $id = $toDelete->getId();

        $this->request($client, '/admin/fx-rates/' . $id . '/delete');
        self::assertTrue($client->getResponse()->isSuccessful());

        $form = $client->getCrawler()->filter('form[name=form]')->form();
        self::assertStringEndsWith($this->createUrl('/admin/fx-rates/' . $id . '/delete'), $form->getUri());
        $client->submit($form);

        $this->assertIsRedirect($client, $this->createUrl('/admin/fx-rates/'));
        $client->followRedirect();
        $this->assertHasDataTable($client);
        $this->assertDataTableRowCount($client, 'datatable_admin_fx_rates', 1);
        $this->assertHasFlashSuccess($client);

        $em = $this->getEntityManager();
        $em->clear();
        self::assertNull($em->getRepository(FxRate::class)->find($id));
        self::assertNotNull($em->getRepository(FxRate::class)->find($remaining->getId()));
    }
}
