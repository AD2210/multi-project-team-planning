<?php

namespace Ad2210\MultiProjectTeamPlanning\Trait;

use DateTimeImmutable;

trait TimeStampTrait
{
    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    public function initTimestamps(): void
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function updateUpdatedAt(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
