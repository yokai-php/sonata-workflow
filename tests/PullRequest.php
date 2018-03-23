<?php

namespace Yokai\SonataWorkflow\Tests;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\Transition;

class PullRequest
{
    private $marking;

    public function getMarking()
    {
        return $this->marking;
    }

    public function setMarking($marking)
    {
        $this->marking = $marking;
    }

    public static function createWorkflowDefinition()
    {
        return new Definition(
            ['opened', 'pending_review', 'merged', 'closed'],
            [
                new Transition('start_review', 'opened', 'pending_review'),
                new Transition('merge', 'pending_review', 'merged'),
                new Transition('close', 'pending_review', 'closed'),
            ],
            'opened'
        );
    }
}
