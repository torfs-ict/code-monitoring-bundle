<?php

declare(strict_types=1);

namespace TorfsICT\Bundle\CodeMonitoringBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use TorfsICT\Bundle\CodeMonitoringBundle\DependencyInjection\CodeMonitoringExtension;

class CodeMonitoringBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension(): CodeMonitoringExtension
    {
        return new CodeMonitoringExtension();
    }
}
