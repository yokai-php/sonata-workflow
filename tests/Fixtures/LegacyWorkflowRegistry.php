<?php

declare(strict_types=1);

namespace Yokai\SonataWorkflow\Tests\Fixtures;

use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\Workflow;

/**
 * This class is provided in order to allow tests to call `add()` method, which
 * is deprecated since symfony/workflow 4.1 and was removed in version 5.0.
 */
final class LegacyWorkflowRegistry extends Registry
{
    public function add(Workflow $workflow, $supportStrategy)
    {
        if (method_exists($this, 'addWorkflow')) {
            $this->addWorkflow($workflow, $supportStrategy);

            return;
        }

        parent::add($workflow, $supportStrategy);
    }
}
