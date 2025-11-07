<?php

namespace Ad2210\MultiProjectTeamPlanning\Interface;


use Symfony\Component\Uid\Uuid;

interface PlanningUserInterface
{
    /** Unique technical identifier (string, UUID ou int, au choix de l’app hôte). */
    public function getId(): string|int|Uuid;

    /** Nom d’affichage (ex: “Jean Dupont”). */
    public function getDisplayName(): string;
}