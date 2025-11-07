<?php

namespace Ad2210\MultiProjectTeamPlanning\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class MultiProjectTeamPlanningExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $locator = new FileLocator(__DIR__ . '/../Resources/config');
        $loader  = new YamlFileLoader($container, $locator);

        // charge le fichier services.yaml du bundle (si présent)
        $loader->load('services.yaml');
    }
}
