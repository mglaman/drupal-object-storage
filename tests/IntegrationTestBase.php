<?php

declare(strict_types=1);

namespace mglaman\DrupalObjectStorage\Test;

use Drupal\Core\DrupalKernel;
use mglaman\DrupalMemoryKernel\MemoryKernelFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class IntegrationTestBase extends TestCase
{
    protected DrupalKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $autoloader = require __DIR__ . '/../vendor/autoload.php';
        $this->kernel = MemoryKernelFactory::get(
            'testing',
            $autoloader,
            [
            'image' => 0,
            ]
        );
    }

    protected function getContainer(): ContainerInterface
    {
        return $this->kernel->getContainer();
    }

    protected function refreshStreamWrappers(): void
    {
        $this->getContainer()->get('stream_wrapper_manager')->register();
    }
}
