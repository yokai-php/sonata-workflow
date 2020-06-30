<?php

declare(strict_types=1);

namespace Yokai\SonataWorkflow\Tests;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel
{
    public function __construct()
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): void
    {
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
    }
}
