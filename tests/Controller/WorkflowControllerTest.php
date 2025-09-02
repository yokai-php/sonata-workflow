<?php

declare(strict_types=1);

namespace Yokai\SonataWorkflow\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Admin\Pool;
use Sonata\AdminBundle\Exception\LockException;
use Sonata\AdminBundle\Exception\ModelManagerException;
use Sonata\AdminBundle\Request\AdminFetcher;
use Sonata\AdminBundle\Templating\MutableTemplateRegistry;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Contracts\Translation\TranslatableInterface;
use Yokai\SonataWorkflow\Tests\Fixtures\StubTranslator;
use Yokai\SonataWorkflow\Tests\PullRequest;
use Yokai\SonataWorkflow\Tests\PullRequestWorkflowController;

/**
 * @author Yann Eugoné <eugone.yann@gmail.com>
 */
class WorkflowControllerTest extends TestCase
{
    use ProphecyTrait;

    private ContainerInterface $container;
    private Request $request;
    private Registry $registry;
    private FlashBag $flashBag;

    /**
     * @var AdminInterface|ObjectProphecy
     */
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->prophesize(AdminInterface::class);

        $this->container = new Container();
        $this->registry = new Registry();
        $this->flashBag = new FlashBag();
        $translator = new StubTranslator();

        $stack = new RequestStack();
        $stack->push($this->request = new Request());

        $this->request->query->set('id', 42);
        $this->request->attributes->set('_sonata_admin', 'admin.pull_request');
        $this->request->setSession(new Session(new MockArraySessionStorage(), null, $this->flashBag));

        $pool = new Pool($this->container, ['admin.pull_request']);

        $this->container->getParameterBag()->set('kernel.debug', true);
        $this->container->set('request_stack', $stack);
        $this->container->set('session', $this->request->getSession());
        $this->container->set('parameter_bag', $this->container->getParameterBag());
        $this->container->set('admin.pull_request', $this->admin->reveal());
        $this->container->set('workflow.registry', $this->registry);
        $this->container->set('translator', $translator);
        $this->container->set('sonata.admin.request.fetcher', new AdminFetcher($pool));

        $this->admin->isChild()->willReturn(false);
        $this->admin->setRequest($this->request)->shouldBeCalled();
        $this->admin->getIdParameter()->willReturn('id');
        $this->admin->getCode()->willReturn('admin.pull_request');
        $this->admin->hasTemplateRegistry()->willReturn(true);
        $this->admin->getTemplateRegistry()->willReturn(new MutableTemplateRegistry());
    }

    public function testWorkflowApplyTransitionActionObjectNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('unable to find the object with id: 42');

        $this->admin->getObject(42)->shouldBeCalledTimes(1)
            ->willReturn(null);

        $this->controller()->workflowApplyTransitionAction($this->request);
    }

    public function testWorkflowApplyTransitionActionWorkflowNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Not found');

        $this->admin->getObject(42)->shouldBeCalledTimes(1)
            ->willReturn($subject = new PullRequest());
        $this->admin->setSubject($subject)->shouldBeCalledTimes(1);
        $this->admin->checkAccess('applyTransitions', $subject)->shouldBeCalledTimes(1);
        $this->admin->getNormalizedIdentifier($subject)->shouldBeCalledTimes(1)->willReturn(42);

        $this->controller()->workflowApplyTransitionAction($this->request);
    }

    public function testWorkflowApplyTransitionActionMissingTransition(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('missing transition to apply');

        $this->registry->addWorkflow(
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

    public function testWorkflowApplyTransitionActionTransitionException(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('transition transition_that_do_not_exists could not be applied to object pr42');

        $this->registry->addWorkflow(
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

    public function testWorkflowApplyTransitionActionModelManagerException(): void
    {
        $this->expectException(ModelManagerException::class);
        $this->expectExceptionMessage('phpunit error');

        $this->registry->addWorkflow(
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

    public function testWorkflowApplyTransitionActionLockException(): void
    {
        $this->registry->addWorkflow(
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

    public function testWorkflowApplyTransitionActionSuccessXmlHttp(): void
    {
        $this->registry->addWorkflow(
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

    public function testWorkflowApplyTransitionActionSuccessHttp(): void
    {
        $this->registry->addWorkflow(
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
        $success = $successes[0];
        self::assertInstanceOf(TranslatableInterface::class, $success);
        self::assertSame('[trans]flash_edit_success[/trans]', $success->trans($this->container->get('translator')));
    }

    public function testWorkflowApplyTransitionActionPreApply(): void
    {
        $this->registry->addWorkflow(
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

    private function controller(): PullRequestWorkflowController
    {
        $controller = new PullRequestWorkflowController();

        $controller->setContainer($this->container);
        $controller->configureAdmin($this->request);
        $controller->autowireWorkflowControllerTrait(
            $this->container->get('workflow.registry'),
            null,
        );

        return $controller;
    }
}
