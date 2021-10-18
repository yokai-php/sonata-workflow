<?php

declare(strict_types=1);

namespace Yokai\SonataWorkflow\Tests;

use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
use Symfony\Component\Workflow\SupportStrategy\WorkflowSupportStrategyInterface;
use Symfony\Component\Workflow\Transition;

class PullRequest
{
    private ?string $marking = null;

    public function getMarking(): ?string
    {
        return $this->marking;
    }

    public function setMarking(string $marking): void
    {
        $this->marking = $marking;
    }

    public static function createWorkflowDefinition(): Definition
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

    public static function createSupportStrategy(): WorkflowSupportStrategyInterface
    {
        return new InstanceOfSupportStrategy(__CLASS__);
    }
}
