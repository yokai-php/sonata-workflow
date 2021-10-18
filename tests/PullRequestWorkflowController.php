<?php

declare(strict_types=1);

namespace Yokai\SonataWorkflow\Tests;

use Symfony\Component\HttpFoundation\Response;
use Yokai\SonataWorkflow\Controller\WorkflowController;

class PullRequestWorkflowController extends WorkflowController
{
    protected function preApplyTransition(object $object, string $transition): ?Response
    {
        if ($transition === 'merge') {
            return new Response('merge');
        }

        return null;
    }
}
