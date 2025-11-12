<?php

namespace Ad2210\MultiProjectTeamPlanning\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('ad2210_mptp');
        $root = $tb->getRootNode();

        $root
            ->children()
                // --- Base ---
                ->scalarNode('timezone')->defaultValue('Europe/Paris')->end()
                ->scalarNode('locale')->defaultValue('fr_FR')->end()
                // endpoint api
                ->arrayNode('api')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('list_url')->defaultNull()->end()                  # ex: /api/planning/slots [GET]
                        ->scalarNode('show_url')->defaultNull()->end()                  # ex: /api/planning/slots/{id} [GET]
                        ->scalarNode('create_url')->defaultNull()->end()                # ex: /api/planning/slots [POST]
                        ->scalarNode('update_url')->defaultNull()->end()                # ex: /api/planning/slots/{id} [PUT/PATCH]
                        ->scalarNode('duplicate_url')->defaultNull()->end()             # ex: /api/planning/slots/{id}/duplicate [POST]
                        ->scalarNode('delete_url')->defaultNull()->end()                # ex: /api/planning/slots/{id} [DELETE]
                        ->scalarNode('csrf_header')->defaultValue('X-CSRF-TOKEN')->end()
                        ->scalarNode('csrf_token_id')->defaultNull()->end()             # ex: planning_api
                    ->end()
                ->end()
                // --- Toolbar (valeurs par défaut communes) ---
                ->arrayNode('toolbar')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('anchor_location')->values(['toolbar', 'sidebar'])->defaultValue('toolbar')->end()
                        ->enumNode('selector_location')->values(['toolbar', 'sidebar'])->defaultValue('toolbar')->end()
                        ->arrayNode('views')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->booleanNode('day')->defaultFalse()->end()
                                ->booleanNode('week')->defaultTrue()->end()
                                ->booleanNode('workweek')->defaultTrue()->end()
                                ->arrayNode('month')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->booleanNode('enable')->defaultTrue()->end()
                                        ->booleanNode('show_weekends')->defaultTrue()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()

                // --- Grid (valeurs par défaut communes) ---
                ->arrayNode('grid')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('work_start')->defaultValue('07:00')->end()
                        ->scalarNode('work_end')->defaultValue('19:00')->end()
                        ->integerNode('overflow_hours')->min(0)->defaultValue(2)->end()
                        ->booleanNode('now_indicator')->defaultTrue()->end()
                        ->booleanNode('highlight_current_day')->defaultTrue()->end()
                        ->integerNode('minute_step')->min(5)->defaultValue(30)->end()
                        ->floatNode('half_hour_opacity')->min(0.0)->max(1.0)->defaultValue(0.25)->end()
                        ->scalarNode('day_label_format')->defaultValue('EEE d MMM')->end()
                        ->scalarNode('time_label_format')->defaultValue('HH:mm')->end()
                    ->end()
                ->end()

                // --- Overrides par mode (global / user / project) ---
                // Chaque mode peut redéfinir partiellement toolbar et/ou grid.
                ->arrayNode('modes')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('toolbar')
                                ->children()
                                    ->enumNode('anchor_location')->values(['toolbar', 'sidebar'])->end()
                                    ->enumNode('selector_location')->values(['toolbar', 'sidebar'])->end()
                                    ->arrayNode('views')
                                        ->children()
                                            ->booleanNode('day')->end()
                                            ->booleanNode('week')->end()
                                            ->booleanNode('workweek')->end()
                                            ->arrayNode('month')
                                                ->addDefaultsIfNotSet()
                                                ->children()
                                                    ->booleanNode('enable')->defaultFalse()->end()
                                                    ->booleanNode('show_weekends')->defaultTrue()->end()
                                                ->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('grid')
                                ->children()
                                    ->scalarNode('work_start')->end()
                                    ->scalarNode('work_end')->end()
                                    ->integerNode('overflow_hours')->min(0)->end()
                                    ->booleanNode('now_indicator')->end()
                                    ->booleanNode('highlight_current_day')->end()
                                    ->integerNode('minute_step')->min(5)->end()
                                    ->floatNode('half_hour_opacity')->min(0.0)->max(1.0)->end()
                                    ->scalarNode('day_label_format')->end()
                                    ->scalarNode('time_label_format')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $tb;
    }
}
