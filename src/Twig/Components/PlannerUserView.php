<?php
namespace Ad2210\MultiProjectTeamPlanning\Twig\Components;

use Ad2210\MultiProjectTeamPlanning\Enum\Period;
use Ad2210\MultiProjectTeamPlanning\Options\PlannerOptions;
use Ad2210\MultiProjectTeamPlanning\Service\TimeGridBuilder;
use Ad2210\MultiProjectTeamPlanning\Service\ViewWindow;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

use DateTimeImmutable;

#[AsLiveComponent('planner_user_view', template: '@MultiProjectTeamPlanning/components/planner_user_view.html.twig')]
final class PlannerUserView
{
    public function __construct(
        private ViewWindow $window,
        private TimeGridBuilder $grid,
        private PlannerOptions $opt,
    ) {}

    #[LiveProp(writable: true)]
    public string $mode = 'user'; // 'user' | 'project' (pour la vue)

    #[LiveProp(writable: true)]
    public ?int $userId = null;

    #[LiveProp(writable: true)]
    public ?int $projectId = null;

    // pour éviter les soucis de binding DateTime, on stocke une string 'Y-m-d'
    #[LiveProp(writable: true)]
    public string $anchor = 'today';

    #[LiveProp(writable: true)]
    public string $period = 'week'; // day|week|workweek|month

    public function getOptions(): PlannerOptions { return $this->opt; }

    public function getAnchorDate(): DateTimeImmutable
    {
        if ($this->anchor === 'today') return new DateTimeImmutable('today', new \DateTimeZone($this->opt->tz()));
        return new DateTimeImmutable($this->anchor, new \DateTimeZone($this->opt->tz()));
    }

    /** @return array{start:\DateTimeImmutable,end:\DateTimeImmutable,days:\DateTimeImmutable[]} */
    public function getWindow(): array
    {
        $period = match ($this->period) {
            'day' => Period::Day, 'workweek' => Period::Workweek, 'month' => Period::Month, default => Period::Week
        };
        return $this->window->compute($this->getAnchorDate(), $period);
    }

    /** @return array{rows:array<int,array{label:string, minute:int}>, start:string, end:string} */
    public function getGrid(): array
    {
        return $this->grid->buildRows();
    }

    #[LiveAction]
    public function today(): void { $this->anchor = 'today'; }

    #[LiveAction]
    public function next(): void {
        $a = $this->getAnchorDate();
        $new = match ($this->period) {
            'day' => $a->modify('+1 day'),
            'workweek','week' => $a->modify('+1 week'),
            'month' => $a->modify('+1 month'),
            default => $a
        };
        $this->anchor = $new->format('Y-m-d'); // <= string OK
    }

    #[LiveAction]
    public function prev(): void {
        $a = $this->getAnchorDate();
        $new = match ($this->period) {
            'day' => $a->modify('-1 day'),
            'workweek','week' => $a->modify('-1 week'),
            'month' => $a->modify('-1 month'),
            default => $a
        };
        $this->anchor = $new->format('Y-m-d');
    }

    #[LiveAction]
    public function setPeriod(string $period): void
    {
        $this->period = $period;
    }

    #[LiveAction]
    public function setAnchor(string $dateYmd): void
    {
        // simple validation
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            $this->anchor = $dateYmd;
        }
    }

    public function getUi(): array {
        return [
            'toolbar' => $this->opt->toolbarFor($this->mode),
            'grid'    => $this->opt->gridFor($this->mode),
        ];
    }
}
