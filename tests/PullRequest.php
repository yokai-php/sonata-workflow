<?php

namespace Yokai\SonataWorkflow\Tests;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\SupportStrategy\ClassInstanceSupportStrategy;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
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

    public static function createSupportStrategy()
    {
        if (class_exists(InstanceOfSupportStrategy::class)) {
            return new InstanceOfSupportStrategy(__CLASS__);
        }

        return new ClassInstanceSupportStrategy(__CLASS__);
    }
}
