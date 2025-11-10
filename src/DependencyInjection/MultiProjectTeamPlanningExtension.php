<?php

namespace Ad2210\MultiProjectTeamPlanning\DependencyInjection;

use Ad2210\MultiProjectTeamPlanning\Options\PlannerOptions;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class MultiProjectTeamPlanningExtension extends Extension
{
    public function getAlias(): string
    {
        return 'ad2210_planning';
    }
    public function load(array $configs, ContainerBuilder $container): void
    {
        $conf = $this->processConfiguration(new Configuration(), $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        if (file_exists(__DIR__.'/../Resources/config/services.yaml')) {
            $loader->load('services.yaml');
        }

        $container->register(PlannerOptions::class)
            ->addArgument($conf)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setPublic(false);
    }

    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('framework')) {
            $projectDir = $container->getParameter('kernel.project_dir');

            $container->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'paths' => [
                        $projectDir.'/vendor/ad2210/multi-project-team-planning/assets/controllers' => '@mptp/controllers',
                        $projectDir.'/vendor/ad2210/multi-project-team-planning/assets/styles' => '@mptp/styles',
                    ],
                ],
            ]);
        }
    }
}
