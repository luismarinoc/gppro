<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller;

use App\Entity\LoginAttempt;
use App\Entity\User;
use App\Repository\LoginAttemptRepository;
use App\Repository\Query\LoginAttemptQuery;
use App\Repository\UserRepository;
use App\Utils\PageSetup;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin-only login audit list.
 *
 * Gated by the native `ROLE_SUPER_ADMIN` role (Symfony's built-in
 * `RoleVoter`), not `RolePermissionManager` — a `RolePermissionManager`
 * entry would be reassignable to `ROLE_ADMIN` via `PermissionController`'s
 * UI, contradicting the proposal's locked "not ROLE_ADMIN" decision
 * (proposal locked decision #5).
 */
#[Route(path: '/admin/login-audit')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class LoginAuditController extends AbstractController
{
    #[Route(path: '/', name: 'admin_login_audit', methods: ['GET'])]
    public function index(Request $request, LoginAttemptRepository $repository, UserRepository $userRepository): Response
    {
        $query = new LoginAttemptQuery();
        $query->setPage($request->query->getInt('page', 1));

        $outcome = $request->query->get('outcome');
        if (\is_string($outcome) && \in_array($outcome, LoginAttempt::OUTCOMES, true)) {
            $query->setOutcome($outcome);
        }

        $userId = $request->query->get('user');
        if (\is_string($userId) && $userId !== '' && ctype_digit($userId)) {
            $selectedUser = $userRepository->find((int) $userId);
            if ($selectedUser instanceof User) {
                $query->setUser($selectedUser);
            }
        }

        $dateFrom = $this->parseDate($request->query->get('dateFrom'));
        if ($dateFrom !== null) {
            $query->setDateFrom($dateFrom);
        }

        $dateTo = $this->parseDate($request->query->get('dateTo'));
        if ($dateTo !== null) {
            $query->setDateTo($dateTo->setTime(23, 59, 59));
        }

        return $this->render('login_audit/index.html.twig', [
            'page_setup' => new PageSetup('login_audit'),
            'pagination' => $repository->getPagerfantaForQuery($query),
            'outcomes' => LoginAttempt::OUTCOMES,
            'selectedOutcome' => $query->getOutcome(),
            'selectedUser' => $query->getUser(),
            'selectedDateFrom' => $query->getDateFrom(),
            'selectedDateTo' => $dateTo,
        ]);
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
