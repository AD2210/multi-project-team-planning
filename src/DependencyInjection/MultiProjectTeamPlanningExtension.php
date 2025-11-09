<?php

namespace Ad2210\MultiProjectTeamPlanning\DependencyInjection;

use Ad2210\MultiProjectTeamPlanning\Options\PlannerOptions;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class MultiProjectTeamPlanningExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $conf = $this->processConfiguration(new Configuration(), $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        if (file_exists(__DIR__.'/../Resources/config/services.yaml')) {
            $loader->load('services.yaml');
        }

        $container->register(PlannerOptions::class)
            ->addArgument($conf)
            ->setPublic(false);
    }
}
