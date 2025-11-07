<?php

namespace Ad2210\MultiProjectTeamPlanning;

use Ad2210\MultiProjectTeamPlanning\DependencyInjection\MultiProjectTeamPlanningExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class MultiProjectTeamPlanningBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new MultiProjectTeamPlanningExtension();
    }
}