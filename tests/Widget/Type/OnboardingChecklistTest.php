<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Widget\Type;

use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\CustomerRepository;
use App\Repository\ProjectRepository;
use App\Repository\Query\ActivityQuery;
use App\Repository\Query\CustomerQuery;
use App\Repository\Query\ProjectQuery;
use App\Repository\Query\UserQuery;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;
use App\Widget\Type\OnboardingChecklist;
use PHPUnit\Framework\Attributes\CoversClass;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

#[CoversClass(OnboardingChecklist::class)]
class OnboardingChecklistTest extends AbstractWidgetTestCase
{
    public function createSut(): OnboardingChecklist
    {
        return $this->createWidget([0, 0, 0, 0, 1]);
    }

    public function getDefaultOptions(): array
    {
        return [];
    }

    private function createWidget(array $counts): OnboardingChecklist
    {
        $user = new User();
        $repositories = [];
        foreach ([
            [CustomerRepository::class, 'countCustomersForQuery', CustomerQuery::class],
            [ProjectRepository::class, 'countProjectsForQuery', ProjectQuery::class],
            [ActivityRepository::class, 'countActivitiesForQuery', ActivityQuery::class],
            [TimesheetRepository::class, 'count', null],
            [UserRepository::class, 'countUsersForQuery', UserQuery::class],
        ] as $index => [$class, $method, $queryClass]) {
            $repository = $this->createMock($class);
            $criteria = ['user' => $user];
            if ($queryClass !== null) {
                $criteria = new $queryClass();
                $criteria->setCurrentUser($user);
            }
            $repository->expects($this->any())->method($method)->with($criteria)->willReturn($counts[$index]);
            $repositories[] = $repository;
        }
        $widget = new OnboardingChecklist(...$repositories);
        $widget->setUser($user);

        return $widget;
    }

    public function testMetadata(): void
    {
        $widget = $this->createSut();
        self::assertSame('OnboardingChecklist', $widget->getId());
        self::assertSame('onboarding.title', $widget->getTitle());
        self::assertSame('widget/widget-onboardingchecklist.html.twig', $widget->getTemplateName());
        self::assertSame([], $widget->getPermissions());
    }

    public function testOrderedIncompleteSteps(): void
    {
        $steps = $this->createSut()->getData();
        self::assertSame(['customer', 'project', 'activity', 'timesheet', 'colleague'], array_column($steps, 'key'));
        self::assertSame(['admin_customer', 'admin_project', 'admin_activity', 'timesheet', 'admin_user'], array_column($steps, 'route'));
        self::assertSame([false, false, false, false, false], array_column($steps, 'done'));
    }

    public function testAllStepsComplete(): void
    {
        self::assertSame([true, true, true, true, true], array_column($this->createWidget([1, 1, 1, 1, 2])->getData(), 'done'));
    }

    public function testChecklistRendering(): void
    {
        $twig = new Environment(new ChainLoader([
            new ArrayLoader(['@theme/embeds/card.html.twig' => '{% block box_title %}{% endblock %}{% block box_body %}{% endblock %}']),
            new FilesystemLoader(__DIR__ . '/../../../templates'),
        ]));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key, array $parameters = []) => $key === 'onboarding.progress' ? $parameters['%completed%'] . '/' . $parameters['%total%'] : $key));
        $granted = false;
        $twig->addFunction(new TwigFunction('is_granted', static function (string $permission, ?string $subject = null) use (&$granted): bool {
            self::assertContains([$permission, $subject], [['listing', 'customer'], ['listing', 'project'], ['listing', 'activity'], ['view_own_timesheet', null], ['view_user', null]]);

            return $granted;
        }));
        $twig->addFunction(new TwigFunction('path', static fn (string $route) => '/' . $route));
        $widget = $this->createWidget([1, 0, 0, 0, 1]);
        $context = ['data' => $widget->getData(), 'options' => []];
        $html = $twig->render($widget->getTemplateName(), $context);
        self::assertStringNotContainsString('<a ', $html);
        self::assertStringContainsString('1/5', $html);
        foreach ($context['data'] as $step) {
            self::assertStringContainsString('onboarding.step.' . $step['key'], $html);
        }
        $granted = true;
        $html = $twig->render($widget->getTemplateName(), $context);
        foreach ($context['data'] as $step) {
            self::assertStringContainsString('href="/' . $step['route'] . '"', $html);
        }
        $context['data'] = $this->createWidget([1, 1, 1, 1, 2])->getData();
        self::assertSame('', trim($twig->render($widget->getTemplateName(), $context)));
    }

    public function testPartialProgress(): void
    {
        self::assertSame([true, false, true, false, false], array_column($this->createWidget([3, 0, 2, 0, 1])->getData(), 'done'));
        self::assertSame([false, false, false, false, false], array_column($this->createWidget([0, 0, 0, 0, 0])->getData(), 'done'));
    }
}
