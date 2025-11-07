<?php
namespace Ad2210\MultiProjectTeamPlanning\Trait;

use Symfony\Component\Uid\Uuid;

trait UuidTrait
{
    private Uuid $id;

    public function getId(): Uuid
    {
        return $this->id;
    }
    public function setId(?Uuid $id = null): void
    {
        $this->id = $id ?? Uuid::v7();
    }
}
