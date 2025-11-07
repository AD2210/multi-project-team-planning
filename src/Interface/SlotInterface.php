<?php

namespace Ad2210\MultiProjectTeamPlanning\Interface;

use DateTimeInterface;

interface SlotInterface
{
    // Core timebox
    public function getStartAt(): DateTimeInterface;

    public function setStartAt(DateTimeInterface $start): static;

    public function getEndAt(): DateTimeInterface;

    public function setEndAt(DateTimeInterface $end): static;

    // Libellé & statut
    public function getTitle(): ?string;

    public function setTitle(?string $title): static;

    public function getStatus(): ?string;

    public function setStatus(?string $status): static;

    // Relations (optionnelles) – laissées à l’app hôte pour l’ORM
    public function getOwner(): ?PlanningUserInterface;

    public function setOwner(?PlanningUserInterface $user): static;

    public function getProject(): ?PlanningProjectInterface;

    public function setProject(?PlanningProjectInterface $project): static;

    // Meta libre pour extensions UI (couleur, tags, etc.)
    public function getContext(): array;

    public function setContext(array $context): static;
}