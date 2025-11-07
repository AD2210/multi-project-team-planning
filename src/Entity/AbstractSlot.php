<?php

namespace Ad2210\MultiProjectTeamPlanning\Entity;

use Ad2210\MultiProjectTeamPlanning\Interface\PlanningProjectInterface;
use Ad2210\MultiProjectTeamPlanning\Interface\PlanningUserInterface;
use Ad2210\MultiProjectTeamPlanning\Interface\SlotInterface;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Base neutre (sans ORM). L’entité concrète de l’app hôte peut :
 * – étendre cette classe
 * – ajouter tes traits (UuidTrait, TimeStampTrait)
 * – déclarer ses mappings (Doctrine ou autre).
 */
abstract class AbstractSlot implements SlotInterface
{
    /** @var DateTimeInterface */
    protected DateTimeInterface $startAt;

    /** @var DateTimeInterface */
    protected DateTimeInterface $endAt;

    protected ?string $title = null;
    protected ?string $status = null;

    protected ?PlanningUserInterface $owner = null;
    protected ?PlanningProjectInterface $project = null;

    /** Données libres (ex: couleur, icône, tags, source, etc.). */
    protected array $context = [];

    public function __construct(DateTimeInterface $startAt, DateTimeInterface $endAt)
    {
        // Normalise en immutable pour éviter les surprises
        $this->startAt = $startAt instanceof DateTimeImmutable ? $startAt : DateTimeImmutable::createFromInterface($startAt);
        $this->endAt   = $endAt   instanceof DateTimeImmutable ? $endAt   : DateTimeImmutable::createFromInterface($endAt);

        $this->guardChronology();
    }

    /** Garantit start < end. */
    protected function guardChronology(): void
    {
        if ($this->startAt >= $this->endAt) {
            throw new InvalidArgumentException('Slot interval invalid: start must be strictly before end.');
        }
    }

    // ---- Timebox ----
    public function getStartAt(): DateTimeInterface
    {
        return $this->startAt;
    }

    public function setStartAt(DateTimeInterface $start): static
    {
        $this->startAt = $start instanceof DateTimeImmutable ? $start : DateTimeImmutable::createFromInterface($start);
        $this->guardChronology();

        return $this;
    }

    public function getEndAt(): DateTimeInterface
    {
        return $this->endAt;
    }

    public function setEndAt(DateTimeInterface $end): static
    {
        $this->endAt = $end instanceof DateTimeImmutable ? $end : DateTimeImmutable::createFromInterface($end);
        $this->guardChronology();

        return $this;
    }

    // ---- Metadata ----
    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    // ---- Relations (non-mappées ici) ----
    public function getOwner(): ?PlanningUserInterface
    {
        return $this->owner;
    }

    public function setOwner(?PlanningUserInterface $user): static
    {
        $this->owner = $user;

        return $this;
    }

    public function getProject(): ?PlanningProjectInterface
    {
        return $this->project;
    }

    public function setProject(?PlanningProjectInterface $project): static
    {
        $this->project = $project;

        return $this;
    }

    // ---- Context libre ----
    public function getContext(): array
    {
        return $this->context;
    }

    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    // ---- Helpers utiles pour les futures features (drag/resize/duplicat.) ----

    /** Déplace le slot d’un delta (en secondes). */
    public function shiftSeconds(int $seconds): static
    {
        $this->startAt = $this->startAt->modify(($seconds >= 0 ? '+' : '').$seconds.' seconds');
        $this->endAt   = $this->endAt->modify(($seconds >= 0 ? '+' : '').$seconds.' seconds');

        return $this;
    }

    /** Redimensionne le début du slot d’un delta (en secondes). */
    public function resizeStartSeconds(int $seconds): static
    {
        $this->startAt = $this->startAt->modify(($seconds >= 0 ? '+' : '').$seconds.' seconds');
        $this->guardChronology();

        return $this;
    }

    /** Redimensionne la fin du slot d’un delta (en secondes). */
    public function resizeEndSeconds(int $seconds): static
    {
        $this->endAt = $this->endAt->modify(($seconds >= 0 ? '+' : '').$seconds.' seconds');
        $this->guardChronology();

        return $this;
    }

    /** Clone léger pour duplication (titre/statut/context recopiés, relations conservées). */
    public function duplicate(DateTimeInterface $newStart, ?int $keepDurationSeconds = null): static
    {
        $duration = $keepDurationSeconds ?? ($this->endAt->getTimestamp() - $this->startAt->getTimestamp());
        $copy = clone $this;

        $copy->startAt = $newStart instanceof DateTimeImmutable ? $newStart : DateTimeImmutable::createFromInterface($newStart);
        $copy->endAt   = $copy->startAt->modify('+'.$duration.' seconds');

        // à l’app hôte de régénérer l’UUID/ID via tes traits
        return $copy;
    }
}