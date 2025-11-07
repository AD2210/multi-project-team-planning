<?php

namespace Ad2210\MultiProjectTeamPlanning\Interface;

use Symfony\Component\Uid\Uuid;

interface PlanningProjectInterface
{
    /** Unique technical identifier (string UUID ou int, au choix de l’app hôte). */
    public function getId(): string|int|Uuid;

    /** Nom d’affichage (ex: “Chantier – Rénovation toiture”). */
    public function getName(): string;
}