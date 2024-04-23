<?php

declare(strict_types=1);

namespace TorfsICT\Bundle\CodeMonitoringBundle\Tests;

use TorfsICT\Tests\Symfony\BundleCompatibilityTestCase;

class SymfonyCompatibilityTest extends BundleCompatibilityTestCase
{
    public function flexEndpointProvider(): ?string
    {
        return 'https://api.github.com/repos/torfs-ict/symfony-flex-recipes/contents/index.json';
    }

    public function packageNameProvider(): string
    {
        return 'torfs-ict/code-monitoring-bundle';
    }

    public function packageRootProvider(): string
    {
        return __DIR__.'/../';
    }

    public function packageVersionProvider(): string
    {
        return '1.0.0';
    }

    public function symfonyVersionProvider(): array
    {
        return [['5.4'], ['6.4'], ['7.0']];
    }

    public function postCompatibilityTest(string $version): void
    {
        $this->assertServiceExists('torfs_io_monitoring.exception_listener', 'prod');
        $this->assertServiceExists('torfs_io_monitoring.writer', 'prod');
    }
}
