<?php

declare(strict_types=1);

namespace Yokai\SonataWorkflow\Translator;

use Sonata\AdminBundle\Admin\AdminInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

interface TranslatorInterface
{
    public function transitionSuccessFlashMessage(
        AdminInterface $admin,
        WorkflowInterface $workflow,
        object $object,
        string $transition,
    ): TranslatableInterface;

    public function transitionErrorFlashMessage(
        AdminInterface $admin,
        WorkflowInterface $workflow,
        object $object,
        string $transition,
    ): TranslatableInterface;
}
