<?php

declare(strict_types=1);

namespace Yokai\SonataWorkflow\Tests\Admin\Extension;

use Generator;
use Knp\Menu\MenuFactory;
use Knp\Menu\MenuItem;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\AdminBundle\Translator\LabelTranslatorStrategyInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\StateMachine;
use Yokai\SonataWorkflow\Admin\Extension\WorkflowExtension;
use Yokai\SonataWorkflow\Controller\WorkflowController;
use Yokai\SonataWorkflow\Tests\PullRequest;

/**
 * @author Yann Eugoné <eugone.yann@gmail.com>
 */
class WorkflowExtensionTest extends TestCase
{
    use ProphecyTrait;

    public function testConfigureRoutes(): void
    {
        /** @var AdminInterface|ObjectProphecy $admin */
        $admin = $this->prophesize(AdminInterface::class);
        $admin->getRouterIdParameter()->willReturn('{id}');

        $collection = new RouteCollection('pull_request', 'pull_request', '/pull-request', WorkflowController::class);
        $extension = new WorkflowExtension(new Registry());
        $extension->configureRoutes($admin->reveal(), $collection);

        self::assertTrue($collection->has('workflow_apply_transition'));
        self::assertInstanceOf(Route::class, $route = $collection->get('workflow_apply_transition'));
        self::assertSame('/pull-request/{id}/workflow/transition/{transition}/apply', $route->getPath());
        self::assertNotEmpty($defaults = $route->getDefaults());
        self::assertArrayHasKey('_controller', $defaults);
        self::assertStringStartsWith(WorkflowController::class, $defaults['_controller']);
        self::assertStringEndsWith('workflowApplyTransitionAction', $defaults['_controller']);
        self::assertArrayHasKey('_sonata_admin', $defaults);
        self::assertSame('pull_request', $defaults['_sonata_admin']);
    }

    public function testAlterNewInstanceWithoutWorkflow(): void
    {
        /** @var AdminInterface|ObjectProphecy $admin */
        $admin = $this->prophesize(AdminInterface::class);

        $extension = new WorkflowExtension(new Registry());
        $extension->alterNewInstance($admin->reveal(), $pullRequest = new PullRequest());

        self::assertNull($pullRequest->getMarking());
    }

    public function testAlterNewInstance(): void
    {
        /** @var AdminInterface|ObjectProphecy $admin */
        $admin = $this->prophesize(AdminInterface::class);

        $registry = new Registry();
        $registry->addWorkflow(
            new StateMachine(PullRequest::createWorkflowDefinition()),
            PullRequest::createSupportStrategy()
        );

        $extension = new WorkflowExtension($registry);
        $extension->alterNewInstance($admin->reveal(), $pullRequest = new PullRequest());

        self::assertSame('opened', $pullRequest->getMarking());
    }

    public function testAccessMapping(): void
    {
        /** @var AdminInterface|ObjectProphecy $admin */
        $admin = $this->prophesize(AdminInterface::class);

        $extension = new WorkflowExtension(new Registry());
        self::assertSame(
            ['viewTransitions' => 'EDIT', 'applyTransitions' => 'EDIT'],
            $extension->getAccessMapping($admin->reveal())
        );
    }

    public function testConfigureTabMenuWithoutSubject(): void
    {
        /** @var AdminInterface|ObjectProphecy $admin */
        $admin = $this->prophesize(AdminInterface::class);
        $admin->getSubject()->willThrow(new \LogicException());

        $extension = new WorkflowExtension(new Registry());
        $extension->configureTabMenu($admin->reveal(), $menu = new MenuItem('root', new MenuFactory()), 'edit');

        self::assertFalse($menu->hasChildren());
    }

    public function testConfigureTabMenuWithoutPermission(): void
    {
        /** @var AdminInterface|ObjectProphecy $admin */
        $admin = $this->prophesize(AdminInterface::class);
        $admin->getSubject()->willReturn($pullRequest = new PullRequest());
        $admin->checkAccess('viewTransitions', $pullRequest)->willThrow(new AccessDeniedException());

        $extension = new WorkflowExtension(new Registry());
        $extension->configureTabMenu($admin->reveal(), $menu = new MenuItem('root', new MenuFactory()), 'edit');

        self::assertFalse($menu->hasChildren());
    }

    public function testConfigureTabMenuWithoutWorkflow(): void
    {
        /** @var AdminInterface|ObjectProphecy $admin */
        $admin = $this->prophesize(AdminInterface::class);
        $admin->getSubject()->willReturn($pullRequest = new PullRequest());
        $admin->checkAccess('viewTransitions', $pullRequest)->shouldBeCalled();

        $extension = new WorkflowExtension(new Registry());
        $extension->configureTabMenu($admin->reveal(), $menu = new MenuItem('root', new MenuFactory()), 'edit');

        self::assertFalse($menu->hasChildren());
    }

    /**
     * @dataProvider markingToTransition
     */
    public function testConfigureTabMenu(string $marking, array $transitions, bool $grantedApply): void
    {
        $pullRequest = new PullRequest();
        $pullRequest->setMarking($marking);

        /** @var LabelTranslatorStrategyInterface|ObjectProphecy $labelStrategy */
        $labelStrategy = $this->prophesize(LabelTranslatorStrategyInterface::class);

        /** @var AdminInterface|ObjectProphecy $admin */
        $admin = $this->prophesize(AdminInterface::class);
        $admin->getTranslationDomain()->willReturn('admin');
        $admin->getLabelTranslatorStrategy()->willReturn($labelStrategy->reveal());
        $admin->getSubject()->willReturn($pullRequest);
        $admin->checkAccess('viewTransitions', $pullRequest)->shouldBeCalled();
        if ($grantedApply) {
            $admin->checkAccess('applyTransitions', $pullRequest)->shouldBeCalledTimes(count($transitions));
        } else {
            $admin->checkAccess('applyTransitions', $pullRequest)->willThrow(new AccessDeniedException());
        }

        foreach ($transitions as $transition) {
            $labelStrategy->getLabel($transition, 'workflow', 'transition')
                ->shouldBeCalledTimes(1)
                ->willReturn('workflow.transition.' . $transition);
            if ($grantedApply) {
                $admin->generateObjectUrl('workflow_apply_transition', $pullRequest, ['transition' => $transition])
                    ->shouldBeCalledTimes(1)
                    ->willReturn('/pull-request/42/workflow/transition/' . $transition . '/apply');
            } else {
                $admin->generateObjectUrl('workflow_apply_transition', $pullRequest, ['transition' => $transition])
                    ->shouldNotBeCalled();
            }
        }

        $registry = new Registry();
        $registry->addWorkflow(
            new StateMachine(PullRequest::createWorkflowDefinition()),
            PullRequest::createSupportStrategy()
        );

        $options = [
            'no_transition_display' => true,
            'transitions_icons' => ['merge' => 'fa fa-times'],
        ];
        $extension = new WorkflowExtension($registry, $options);
        $extension->configureTabMenu($admin->reveal(), $menu = new MenuItem('root', new MenuFactory()), 'edit');

        if (count($transitions) === 0) {
            self::assertNull($child = $menu->getChild('workflow_transitions'));
            self::assertNotNull($child = $menu->getChild('workflow_transitions_empty'));
            self::assertSame('#', $child->getUri());
            self::assertSame('admin', $child->getExtra('translation_domain'));
            self::assertFalse($child->hasChildren());
            self::assertEmpty($child->getChildren());
        } else {
            self::assertNull($child = $menu->getChild('workflow_transitions_empty'));
            self::assertNotNull($child = $menu->getChild('workflow_transitions'));
            self::assertSame('admin', $child->getExtra('translation_domain'));
            self::assertTrue($child->getAttribute('dropdown'));
            self::assertSame('fa fa-code-fork', $child->getAttribute('icon'));
            self::assertTrue($child->hasChildren());
            self::assertCount(count($transitions), $child->getChildren());
            foreach ($transitions as $transition) {
                $icon = null;
                if ($transition === 'merge') {
                    $icon = 'fa fa-times';
                }

                self::assertNotNull($item = $child->getChild('workflow.transition.' . $transition));
                if ($grantedApply) {
                    self::assertSame('/pull-request/42/workflow/transition/' . $transition . '/apply', $item->getUri());
                } else {
                    self::assertNull($item->getUri());
                }
                self::assertSame('admin', $item->getExtra('translation_domain'));
                self::assertSame($icon, $item->getAttribute('icon'));
            }
        }
    }

    public function markingToTransition(): Generator
    {
        foreach ([true, false] as $grantedApply) {
            $grantedApplyStr = $grantedApply ? 'with links' : 'without links';

            yield 'opened ' . $grantedApplyStr => ['opened', ['start_review'], $grantedApply];
            yield 'pending_review ' . $grantedApplyStr => ['pending_review', ['merge', 'close'], $grantedApply];
            yield 'closed ' . $grantedApplyStr => ['closed', [], $grantedApply];
        }
    }
}
