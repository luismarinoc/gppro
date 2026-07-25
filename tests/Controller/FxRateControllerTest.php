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
 * PR4 covers permission boundaries only: the fx_rates/*.html.twig templates are
 * added in PR5, so success-path rendering (list/create/edit 200 responses) is
 * intentionally out of scope here and lives in the PR5 functional suite instead.
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
        // before the controller body runs, regardless of the (not yet existing)
        // fx_rates/index.html.twig template.
        $this->request($client, '/admin/fx-rates/');

        // Confirmed via manual run: currently 500 (LoaderError, no template yet in
        // PR4) rather than 200 - never 403. PR5 adds the template and this becomes
        // a real 200, without needing to change this assertion.
        self::assertNotEquals(403, $client->getResponse()->getStatusCode());
    }
}
