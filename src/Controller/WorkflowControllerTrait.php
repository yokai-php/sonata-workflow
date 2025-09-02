<?php

declare(strict_types=1);

namespace Yokai\SonataWorkflow\Controller;

use Sonata\AdminBundle\Exception\LockException;
use Sonata\AdminBundle\Exception\ModelManagerException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Workflow\Exception\InvalidArgumentException;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Yokai\SonataWorkflow\Translator\StandardTranslator;
use Yokai\SonataWorkflow\Translator\TranslatorInterface;

/**
 *
 * @author Yann Eugoné <eugone.yann@gmail.com>
 */
trait WorkflowControllerTrait
{
    private Registry $workflowRegistry;
    private TranslatorInterface $translator;

    #[Required]
    public function autowireWorkflowControllerTrait(
        Registry $workflowRegistry,
        TranslatorInterface|null $translator,
    ): void {
        $this->workflowRegistry = $workflowRegistry;
        $this->translator = $translator ?? new StandardTranslator();
    }

    public function workflowApplyTransitionAction(Request $request): Response
    {
        $id = $request->get($this->admin->getIdParameter());

        $existingObject = $this->admin->getObject($id);

        if (!$existingObject) {
            throw $this->createNotFoundException(sprintf('unable to find the object with id: %s', $id));
        }

        $this->admin->setSubject($existingObject);
        $this->admin->checkAccess('applyTransitions', $existingObject);

        $objectId = $this->admin->getNormalizedIdentifier($existingObject);

        try {
            $workflow = $this->getWorkflow($existingObject);
        } catch (InvalidArgumentException $exception) {
            throw $this->createNotFoundException('Not found', $exception);
        }

        $transition = $request->get('transition', null);
        if ($transition === null) {
            throw new BadRequestHttpException('missing transition to apply');
        }

        if (!$workflow->can($existingObject, $transition)) {
            throw new BadRequestHttpException(
                sprintf(
                    'transition %s could not be applied to object %s',
                    $transition,
                    $this->admin->toString($existingObject)
                )
            );
        }

        $response = $this->preApplyTransition($existingObject, $transition);
        if ($response !== null) {
            return $response;
        }

        try {
            $workflow->apply($existingObject, $transition);
            $existingObject = $this->admin->update($existingObject);

            if ($this->isXmlHttpRequest($request)) {
                return $this->renderJson(
                    [
                        'result' => 'ok',
                        'objectId' => $objectId,
                        'objectName' => $this->escapeHtml($this->admin->toString($existingObject)),
                    ],
                    200,
                    []
                );
            }

            $this->addFlash(
                'sonata_flash_success',
                $this->translator->transitionSuccessFlashMessage($this->admin, $workflow, $existingObject, $transition),
            );
        } catch (LogicException $e) {
            throw new BadRequestHttpException(
                sprintf(
                    'transition %s could not be applied to object %s',
                    $transition,
                    $this->admin->toString($existingObject)
                ),
                $e
            );
        } catch (ModelManagerException $e) {
            $this->handleModelManagerException($e);
            $this->addFlash(
                'sonata_flash_error',
                $this->translator->transitionErrorFlashMessage($this->admin, $workflow, $existingObject, $transition),
            );
        } catch (LockException $e) {
            $this->addFlash(
                'sonata_flash_error',
                $this->trans(
                    'flash_lock_error',
                    [
                        '%name%' => $this->escapeHtml($this->admin->toString($existingObject)),
                        '%link_start%' => '<a href="' . $this->admin->generateObjectUrl('edit', $existingObject) . '">',
                        '%link_end%' => '</a>',
                    ],
                    'SonataAdminBundle'
                )
            );
        }

        return $this->redirectTo($request, $existingObject);
    }

    /**
     * @throws InvalidArgumentException
     */
    final protected function getWorkflow(object $object): WorkflowInterface
    {
        if (!isset($this->workflowRegistry)) {
            throw new \LogicException('Workflow registry was not set on controller.');
        }

        return $this->workflowRegistry->get($object);
    }

    final protected function getWorkflowTranslator(): TranslatorInterface
    {
        return $this->translator ?? new StandardTranslator();
    }

    protected function preApplyTransition(object $object, string $transition): ?Response
    {
        return null;
    }
}
