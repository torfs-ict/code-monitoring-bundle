<?php

declare(strict_types=1);

namespace TorfsICT\Bundle\CodeMonitoringBundle\Polyfill;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurableExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Bundle\BundleExtension;

/**
 * @deprecated Use the HttpKernel class when upgrading to Symfony 6.4.
 */
abstract class AbstractBundle extends Bundle implements ConfigurableExtensionInterface
{
    protected string $extensionAlias = '';

    public function configure(DefinitionConfigurator $definition): void
    {
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if ('' === $this->extensionAlias) {
            $this->extensionAlias = Container::underscore(preg_replace('/Bundle$/', '', $this->getName()));
        }

        return $this->extension ??= new BundleExtension($this, $this->extensionAlias);
    }

    public function getPath(): string
    {
        if (!isset($this->path)) {
            $reflected = new \ReflectionObject($this);
            // assume the modern directory structure by default
            $this->path = \dirname($reflected->getFileName(), 2);
        }

        return $this->path;
    }
}
