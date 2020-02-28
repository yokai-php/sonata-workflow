<?php

namespace Yokai\SonataWorkflow\Tests\Controller;

use Prophecy\Prophecy\ObjectProphecy;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Admin\Pool;
use Sonata\AdminBundle\Exception\LockException;
use Sonata\AdminBundle\Exception\ModelManagerException;
use Sonata\AdminBundle\Templating\TemplateRegistryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\StateMachine;
use Yokai\SonataWorkflow\Tests\Fixtures\LegacyWorkflowRegistry;
use Yokai\SonataWorkflow\Tests\Fixtures\StubTranslator;
use Yokai\SonataWorkflow\Tests\PullRequest;
use Yokai\SonataWorkflow\Tests\PullRequestWorkflowController;
use Yokai\SonataWorkflow\Tests\TestKernel;

/**
 * @author Yann Eugoné <eugone.yann@gmail.com>
 */
class WorkflowControllerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ContainerInterface|ObjectProphecy
     */
    private $container;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var AdminInterface|ObjectProphecy
     */
    private $admin;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var FlashBag
     */
    private $flashBag;

    /**
     * @var StubTranslator
     */
    private $translator;

    public function setUp()
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->admin = $this->prophesize(AdminInterface::class);
        $this->registry = new LegacyWorkflowRegistry();
        $this->flashBag = new FlashBag();
        $this->translator = new StubTranslator();

        $stack = new RequestStack();
        $stack->push($this->request = new Request());

        $this->request->query->set('id', 42);
        $this->request->attributes->set('_sonata_admin', 'admin.pull_request');

        $pool = new Pool($this->container->reveal(), 'phpunit', 'phpunit');
        $pool->setAdminServiceIds(['admin.pull_request']);

        $this->container->get('request_stack')->willReturn($stack);
        $this->container->get('sonata.admin.pool')->willReturn($pool);
        $this->container->get('admin.pull_request')->willReturn($this->admin->reveal());
        $this->container->get('workflow.registry')->willReturn($this->registry);
        $this->container->get('kernel')->willReturn(new TestKernel());
        $this->container->has('session')->willReturn(true);
        $this->container->get('session')->willReturn(new Session(new MockArraySessionStorage(), null, $this->flashBag));
        $this->container->get('translator')->willReturn($this->translator);
        $this->container->has('logger')->willReturn(false);
        $this->container->get('admin.pull_request.template_registry')->willReturn($this->prophesize(TemplateRegistryInterface::class)->reveal());

        $this->admin->isChild()->willReturn(false);
        $this->admin->setRequest($this->request)->willReturn(null);
        $this->admin->getIdParameter()->willReturn('id');
        $this->admin->getCode()->willReturn('admin.pull_request');
    }

    public function tearDown()
    {
        $this->container =
        $this->request =
        $this->admin =
        $this->registry =
        $this->flashBag =
        $this->translator = null;
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @expectedExceptionMessage unable to find the object with id: 42
     */
    public function testWorkflowApplyTransitionActionObjectNotFound()
    {
        $this->admin->getObject(42)->shouldBeCalledTimes(1)
            ->willReturn(null);

        $this->controller()->workflowApplyTransitionAction($this->request);
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @expectedExceptionMessage Not found
     */
    public function testWorkflowApplyTransitionActionWorkflowNotFound()
    {
        $this->admin->getObject(42)->shouldBeCalledTimes(1)
            ->willReturn($subject = new PullRequest());
        $this->admin->setSubject($subject)->shouldBeCalledTimes(1);
        $this->admin->checkAccess('applyTransitions', $subject)->shouldBeCalledTimes(1);
        $this->admin->getNormalizedIdentifier($subject)->shouldBeCalledTimes(1)->willReturn(42);

        $this->controller()->workflowApplyTransitionAction($this->request);
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     * @expectedExceptionMessage missing transition to apply
     */
    public function testWorkflowApplyTransitionActionMissingTransition()
    {
        $this->registry->add(
            new StateMachine(PullRequest::createWorkflowDefinition()),
            PullRequest::createSupportStrategy()
        );

        $this->admin->getObject(42)->shouldBeCalledTimes(1)
            ->willReturn($subject = new PullRequest());
        $this->admin->setSubject($subject)->shouldBeCalledTimes(1);
        $this->admin->checkAccess('applyTransitions', $subject)->shouldBeCalledTimes(1);
        $this->admin->getNormalizedIdentifier($subject)->shouldBeCalledTimes(1)->willReturn(42);

        $this->controller()->workflowApplyTransitionAction($this->request);
    }

    /**
     * @expectedException \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
     * @expectedExceptionMessage transition transition_that_do_not_exists could not be applied to object pr42
     */
    public function testWorkflowApplyTransitionActionTransitionException()
    {
        $this->registry->add(
            new StateMachine(PullRequest::createWorkflowDefinition()),
            PullRequest::createSupportStrategy()
        );

        $this->request->attributes->set('transition', 'transition_that_do_not_exists');

        $this->admin->getObject(42)->shouldBeCalledTimes(1)
            ->willReturn($subject = new PullRequest());
        $this->admin->toString($subject)->shouldBeCalledTimes(1)->willReturn('pr42');
        $this->admin->setSubject($subject)->shouldBeCalledTimes(1);
        $this->admin->checkAccess('applyTransitions', $subject)->shouldBeCalledTimes(1);
        $this->admin->getNormalizedIdentifier($subject)->shouldBeCalledTimes(1)->willReturn(42);

        $this->controller()->workflowApplyTransitionAction($this->request);
    }

    /**
     * @expectedException \Sonata\AdminBundle\Exception\ModelManagerException
     * @expectedExceptionMessage phpunit error
     */
    public function testWorkflowApplyTransitionActionModelManagerException()
    {
        $this->registry->add(
            new StateMachine(PullRequest::createWorkflowDefinition()),
            PullRequest::createSupportStrategy()
        );

        $this->request->attributes->set('transition', 'start_review');

        $this->admin->getObject(42)->shouldBeCalledTimes(1)
            ->willReturn($subject = new PullRequest());
        $this->admin->setSubject($subject)->shouldBeCalledTimes(1);
        $this->admin->checkAccess('applyTransitions', $subject)->shouldBeCalledTimes(1);
        $this->admin->getNormalizedIdentifier($subject)->shouldBeCalledTimes(1)->willReturn(42);
        $this->admin->update($subject)->shouldBeCalledTimes(1)->willThrow(new ModelManagerException('phpunit error'));

        $subject->setMarking('opened');

        $this->controller()->workflowApplyTransitionAction($this->request);
    }

    public function testWorkflowApplyTransitionActionLockException()
    {
        $this->registry->add(
            new StateMachine(PullRequest::createWorkflowDefinition()),
            PullRequest::createSupportStrategy()
        );

        $this->request->attributes->set('transition', 'start_review');

        $this->admin->getObject(42)->shouldBeCalledTimes(1)
            ->willReturn($subject = new PullRequest());
        $this->admin->toString($subject)->shouldBeCalledTimes(1)->willReturn('pr42');
        $this->admin->generateObjectUrl('edit', $subject)->shouldBeCalledTimes(1)
            ->willReturn('/pull-request/42/edit');
        $this->admin->setSubject($subject)->shouldBeCalledTimes(1);
        $this->admin->checkAccess('applyTransitions', $subject)->shouldBeCalledTimes(1);
        $this->admin->getNormalizedIdentifier($subject)->shouldBeCalledTimes(1)->willReturn(42);
        $this->admin->hasRoute('edit')->shouldBeCalledTimes(1)->willReturn(false);
        $this->admin->hasRoute('show')->shouldBeCalledTimes(1)->willReturn(false);
        $this->admin->getFilterParameters()->shouldBeCalledTimes(1)->willReturn([]);
        $this->admin->generateUrl('list', [])->shouldBeCalledTimes(1)->willReturn('/pull-request/list');
        $this->admin->update($subject)->shouldBeCalledTimes(1)->willThrow(new LockException('phpunit error'));

        $subject->setMarking('opened');

        /** @var RedirectResponse $response */
        $response = $this->controller()->workflowApplyTransitionAction($this->request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/pull-request/list', $response->getTargetUrl());

        $errors = $this->flashBag->peek('sonata_flash_error');
        self::assertCount(1, $errors);
        self::assertSame('[trans]flash_lock_error[/trans]', $errors[0]);
    }

    public function testWorkflowApplyTransitionActionSuccessXmlHttp()
    {
        $this->registry->add(
            new StateMachine(PullRequest::createWorkflowDefinition()),
            PullRequest::createSupportStrategy()
        );

        $this->request->attributes->set('transition', 'start_review');
        $this->request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $this->admin->getObject(42)->shouldBeCalledTimes(1)
            ->willReturn($subject = new PullRequest());
        $this->admin->toString($subject)->shouldBeCalledTimes(1)->willReturn('pr42');
        $this->admin->setSubject($subject)->shouldBeCalledTimes(1);
        $this->admin->checkAccess('applyTransitions', $subject)->shouldBeCalledTimes(1);
        $this->admin->getNormalizedIdentifier($subject)->shouldBeCalledTimes(1)->willReturn(42);
        $this->admin->update($subject)->shouldBeCalledTimes(1)->willReturn($subject);

        $subject->setMarking('opened');

        /** @var RedirectResponse $response */
        $response = $this->controller()->workflowApplyTransitionAction($this->request);

        self::assertInstanceOf(JsonResponse::class, $response);

        $errors = $this->flashBag->peek('sonata_flash_error');
        self::assertCount(0, $errors);
    }

    public function testWorkflowApplyTransitionActionSuccessHttp()
    {
        $this->registry->add(
            new StateMachine(PullRequest::createWorkflowDefinition()),
            PullRequest::createSupportStrategy()
        );

        $this->request->attributes->set('transition', 'start_review');

        $this->admin->getObject(42)->shouldBeCalledTimes(1)
            ->willReturn($subject = new PullRequest());
        $this->admin->toString($subject)->shouldBeCalledTimes(1)->willReturn('pr42');
        $this->admin->setSubject($subject)->shouldBeCalledTimes(1);
        $this->admin->checkAccess('applyTransitions', $subject)->shouldBeCalledTimes(1);
        $this->admin->getNormalizedIdentifier($subject)->shouldBeCalledTimes(1)->willReturn(42);
        $this->admin->hasRoute('edit')->shouldBeCalledTimes(1)->willReturn(false);
        $this->admin->hasRoute('show')->shouldBeCalledTimes(1)->willReturn(false);
        $this->admin->getFilterParameters()->shouldBeCalledTimes(1)->willReturn([]);
        $this->admin->generateUrl('list', [])->shouldBeCalledTimes(1)->willReturn('/pull-request/list');
        $this->admin->update($subject)->shouldBeCalledTimes(1)->willReturn($subject);

        $subject->setMarking('opened');

        /** @var RedirectResponse $response */
        $response = $this->controller()->workflowApplyTransitionAction($this->request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/pull-request/list', $response->getTargetUrl());

        $errors = $this->flashBag->peek('sonata_flash_error');
        self::assertCount(0, $errors);
        $successes = $this->flashBag->peek('sonata_flash_success');
        self::assertCount(1, $successes);
        self::assertSame('[trans]flash_edit_success[/trans]', $successes[0]);
    }

    public function testWorkflowApplyTransitionActionPreApply()
    {
        $this->registry->add(
            new StateMachine(PullRequest::createWorkflowDefinition()),
            PullRequest::createSupportStrategy()
        );

        $this->request->attributes->set('transition', 'merge');

        $this->admin->getObject(42)->shouldBeCalledTimes(1)
            ->willReturn($subject = new PullRequest());
        $this->admin->setSubject($subject)->shouldBeCalledTimes(1);
        $this->admin->checkAccess('applyTransitions', $subject)->shouldBeCalledTimes(1);
        $this->admin->getNormalizedIdentifier($subject)->shouldBeCalledTimes(1)->willReturn(42);
        $this->admin->update($subject)->shouldNotBeCalled();

        $subject->setMarking('pending_review');

        /** @var RedirectResponse $response */
        $response = $this->controller()->workflowApplyTransitionAction($this->request);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('merge', $response->getContent());

        $errors = $this->flashBag->peek('sonata_flash_error');
        self::assertCount(0, $errors);
        $successes = $this->flashBag->peek('sonata_flash_success');
        self::assertCount(0, $successes);
    }

    private function controller()
    {
        $controller = new PullRequestWorkflowController();

        $controller->setContainer($this->container->reveal());

        return $controller;
    }
}
