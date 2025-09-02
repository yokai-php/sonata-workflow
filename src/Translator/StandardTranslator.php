<?php

declare(strict_types=1);

namespace Yokai\SonataWorkflow\Translator;

use Sonata\AdminBundle\Admin\AdminInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

final class StandardTranslator implements TranslatorInterface
{
    public function transitionSuccessFlashMessage(
        AdminInterface $admin,
        WorkflowInterface $workflow,
        object $object,
        string $transition,
    ): TranslatableInterface {
        return new TranslatableMessage(
            message: 'flash_edit_success',
            parameters: [
                '%name%' => $this->escapeHtml($admin->toString($object)),
            ],
            domain: 'SonataAdminBundle',
        );
    }

    public function transitionErrorFlashMessage(
        AdminInterface $admin,
        WorkflowInterface $workflow,
        object $object,
        string $transition,
    ): TranslatableInterface {
        return new TranslatableMessage(
            message: 'flash_edit_error',
            parameters: [
                '%name%' => $this->escapeHtml($admin->toString($object)),
            ],
            domain: 'SonataAdminBundle',
        );
    }

    private function escapeHtml(string $string): string
    {
        return \htmlspecialchars($string, \ENT_QUOTES | \ENT_SUBSTITUTE);
    }
}
