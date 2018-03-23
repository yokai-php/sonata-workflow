<?php

namespace Yokai\SonataWorkflow\Tests\Admin\Extension;

use Knp\Menu\MenuFactory;
use Knp\Menu\MenuItem;
use Prophecy\Prophecy\ObjectProphecy;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Route\RouteCollection;
use Sonata\AdminBundle\Translator\LabelTranslatorStrategyInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\SupportStrategy\ClassInstanceSupportStrategy;
use Yokai\SonataWorkflow\Admin\Extension\WorkflowExtension;
use Yokai\SonataWorkflow\Admin\WorkflowController;
use Yokai\SonataWorkflow\Tests\PullRequest;

/**
 * @author Yann Eugoné <eugone.yann@gmail.com>
 */
class WorkflowExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testConfigureRoutes()
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
        self::assertSame(WorkflowController::class.':workflowApplyTransitionAction', $defaults['_controller']);
        self::assertArrayHasKey('_sonata_admin', $defaults);
        self::assertSame('pull_request', $defaults['_sonata_admin']);
    }

    public function testAlterNewInstanceWithoutWorkflow()
    {
        /** @var AdminInterface|ObjectProphecy $admin */
        $admin = $this->prophesize(AdminInterface::class);

        $extension = new WorkflowExtension(new Registry());
        $extension->alterNewInstance($admin->reveal(), $pullRequest = new PullRequest());

        self::assertNull($pullRequest->getMarking());
    }

    public function testAlterNewInstance()
    {
        /** @var AdminInterface|ObjectProphecy $admin */
        $admin = $this->prophesize(AdminInterface::class);

        $registry = new Registry();
        $registry->add(
            new StateMachine(PullRequest::createWorkflowDefinition()),
            new ClassInstanceSupportStrategy(PullRequest::class)
        );

        $extension = new WorkflowExtension($registry);
        $extension->alterNewInstance($admin->reveal(), $pullRequest = new PullRequest());

        self::assertSame('opened', $pullRequest->getMarking());
    }

    public function testConfigureSideMenuWithoutSubject()
    {
        /** @var AdminInterface|ObjectProphecy $admin */
        $admin = $this->prophesize(AdminInterface::class);
        $admin->getSubject()->willReturn(null);

        $extension = new WorkflowExtension(new Registry());
        $extension->configureSideMenu($admin->reveal(), $menu = new MenuItem('root', new MenuFactory()), 'edit');

        self::assertFalse($menu->hasChildren());
    }

    public function testConfigureSideMenuWithoutWorkflow()
    {
        /** @var AdminInterface|ObjectProphecy $admin */
        $admin = $this->prophesize(AdminInterface::class);
        $admin->getSubject()->willReturn(new PullRequest());

        $extension = new WorkflowExtension(new Registry());
        $extension->configureSideMenu($admin->reveal(), $menu = new MenuItem('root', new MenuFactory()), 'edit');

        self::assertFalse($menu->hasChildren());
    }

    /**
     * @dataProvider markingToTransition
     */
    public function testConfigureSideMenu($marking, array $transitions)
    {
        $pullRequest = new PullRequest();
        $pullRequest->setMarking($marking);

        /** @var LabelTranslatorStrategyInterface|ObjectProphecy $labelStrategy */
        $labelStrategy = $this->prophesize(LabelTranslatorStrategyInterface::class);

        /** @var AdminInterface|ObjectProphecy $admin */
        $admin = $this->prophesize(AdminInterface::class);
        $admin->getLabelTranslatorStrategy()->willReturn($labelStrategy->reveal());
        $admin->getSubject()->willReturn($pullRequest);

        foreach ($transitions as $transition) {
            $labelStrategy->getLabel($transition, 'workflow')
                ->shouldBeCalledTimes(1)
                ->willReturn('transition.'.$transition);
            $admin->generateObjectUrl('workflow_apply_transition', $pullRequest, ['transition' => $transition])
                ->shouldBeCalledTimes(1)
                ->willReturn('/pull-request/42/workflow/transition/'.$transition.'/apply');
        }

        $registry = new Registry();
        $registry->add(
            new StateMachine(PullRequest::createWorkflowDefinition()),
            new ClassInstanceSupportStrategy(PullRequest::class)
        );

        $options = [
            'no_transition_display' => true,
            'transitions_icons' => ['merge' => 'fa fa-times'],
        ];
        $extension = new WorkflowExtension($registry, $options);
        $extension->configureSideMenu($admin->reveal(), $menu = new MenuItem('root', new MenuFactory()), 'edit');

        if (count($transitions) === 0) {
            self::assertNull($child = $menu->getChild('workflow_transitions'));
            self::assertNotNull($child = $menu->getChild('workflow_transitions_empty'));
            self::assertSame('#', $child->getUri());
            self::assertFalse($child->hasChildren());
            self::assertEmpty($child->getChildren());
        } else {
            self::assertNull($child = $menu->getChild('workflow_transitions_empty'));
            self::assertNotNull($child = $menu->getChild('workflow_transitions'));
            self::assertTrue($child->getAttribute('dropdown'));
            self::assertSame('fa fa-code-fork', $child->getAttribute('icon'));
            self::assertTrue($child->hasChildren());
            self::assertCount(count($transitions), $child->getChildren());
            foreach ($transitions as $transition) {
                $icon = null;
                if ($transition === 'merge') {
                    $icon = 'fa fa-times';
                }

                self::assertNotNull($item = $child->getChild('transition.'.$transition));
                self::assertSame('/pull-request/42/workflow/transition/'.$transition.'/apply', $item->getUri());
                self::assertSame($icon, $item->getAttribute('icon'));
            }
        }
    }

    public function markingToTransition()
    {
        return [
            'opened' => ['opened', ['start_review']],
            'pending_review' => ['pending_review', ['merge', 'close']],
            'closed' => ['closed', []],
        ];
    }
}
