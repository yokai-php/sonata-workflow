<?php

namespace Yokai\SonataWorkflow\Tests;

use Symfony\Component\HttpFoundation\Response;
use Yokai\SonataWorkflow\Controller\WorkflowController;

class PullRequestWorkflowController extends WorkflowController
{
    protected function preApplyTransition($object, $transition)
    {
        if ($transition === 'merge') {
            return new Response('merge');
        }

        return null;
    }
}
