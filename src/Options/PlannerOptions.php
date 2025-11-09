<?php

namespace Ad2210\MultiProjectTeamPlanning\Options;

final class PlannerOptions
{
    public function __construct(private array $config) {}

    public function tz(): string { return $this->config['timezone'] ?? 'Europe/Paris'; }

    /** @return array merged defaults + overrides for $mode */
    public function toolbarFor(string $mode): array {
        $base = $this->config['toolbar'] ?? [];
        $ov   = $this->config['modes'][$mode]['toolbar'] ?? [];
        return array_replace_recursive($base, $ov);
    }

    /** @return array merged defaults + overrides for $mode */
    public function gridFor(string $mode): array {
        $base = $this->config['grid'] ?? [];
        $ov   = $this->config['modes'][$mode]['grid'] ?? [];
        return array_replace_recursive($base, $ov);
    }
}