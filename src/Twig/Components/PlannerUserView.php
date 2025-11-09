<?php
namespace Ad2210\MultiProjectTeamPlanning\Twig\Components;

use Ad2210\MultiProjectTeamPlanning\Enum\Period;
use Ad2210\MultiProjectTeamPlanning\Options\PlannerOptions;
use Ad2210\MultiProjectTeamPlanning\Service\DateFormatter;
use Ad2210\MultiProjectTeamPlanning\Service\TimeGridBuilder;
use Ad2210\MultiProjectTeamPlanning\Service\ViewWindow;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use DateTimeImmutable;
use DateTimeZone;

#[AsLiveComponent(
    name: 'planner_user_view',
    template: '@MultiProjectTeamPlanning/components/planner_user_view.html.twig'
)]
final class PlannerUserView
{
    use DefaultActionTrait;

    public function __construct(
        private ViewWindow $window,
        private TimeGridBuilder $grid,
        private PlannerOptions $opt,
        private ?CsrfTokenManagerInterface $csrf = null,
    ) {}

    #[LiveProp(writable: true)]
    public string $mode = 'user'; // 'user' | 'project'

    #[LiveProp(writable: true)]
    public ?int $userId = null;

    #[LiveProp(writable: true)]
    public ?int $projectId = null;

    #[LiveProp(writable: true)]
    public string $anchor = 'today'; // 'YYYY-MM-DD' | 'today'

    #[LiveProp(writable: true)]
    public string $period = 'week'; // day|week|workweek|month

    public function getOptions(): PlannerOptions { return $this->opt; }

    public function getAnchorDate(): DateTimeImmutable
    {
        $tz = new DateTimeZone($this->opt->tz());
        return $this->anchor === 'today'
            ? new DateTimeImmutable('today', $tz)
            : new DateTimeImmutable($this->anchor, $tz);
    }

    /** @return array{start:\DateTimeImmutable,end:\DateTimeImmutable,days:\DateTimeImmutable[]} */
    public function getWindow(): array
    {
        $period = match ($this->period) {
            'day' => Period::Day,
            'workweek' => Period::Workweek,
            'month' => Period::Month,
            default => Period::Week
        };
        return $this->window->compute($this->getAnchorDate(), $period);
    }

    /** @return array{rows:array<int,array{label:string, minute:int}>, start:string, end:string} */
    public function getGrid(): array
    {
        return $this->grid->buildRows($this->mode);
    }

    /** Prépare les jours affichés avec label déjà formaté selon YAML (une seule fois).
     *  @return array<int, array{date:\DateTimeImmutable,label:string}>
     */
    public function getDaysForView(): array
    {
        $g   = $this->opt->gridFor($this->mode);
        $icu = $g['day_label_format'] ?? 'EEE d MMM';

        $days = [];
        foreach ($this->getWindow()['days'] as $d) {
            $days[] = [
                'date'  => $d,
                'label' => DateFormatter::formatIcu($d, $icu, $this->opt->locale(), $this->opt->tz()),
            ];
        }
        return $days;
    }

    public function getUi(): array
    {
        return [
            'toolbar' => $this->opt->toolbarFor($this->mode),
            'grid'    => $this->opt->gridFor($this->mode),
            'api'     => $this->opt->api(),
            'tz'      => $this->opt->tz(),
            'locale'  => $this->opt->locale(),
        ];
    }

    public function getCsrfTokenValue(): string
    {
        $api = $this->opt->api();
        $id = $api['csrf_token_id'] ?? null;
        if (!$id || !$this->csrf) {
            return '';
        }
        return $this->csrf->getToken($id)->getValue();
    }

    #[LiveAction]
    public function today(): void { $this->anchor = 'today'; }

    #[LiveAction]
    public function next(): void
    {
        $a = $this->getAnchorDate();
        $new = match ($this->period) {
            'day' => $a->modify('+1 day'),
            'workweek','week' => $a->modify('+1 week'),
            'month' => $a->modify('+1 month'),
            default => $a
        };
        $this->anchor = $new->format('Y-m-d');
    }

    #[LiveAction]
    public function prev(): void
    {
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
    public function setPeriod(string $period): void { $this->period = $period; }

    public function getRangeStartYmd(): string
    {
        $days = array_values($this->getWindow()['days']);
        return $days[0]->format('Y-m-d');
    }

    public function getRangeEndYmd(): string
    {
        $days = array_values($this->getWindow()['days']);
        return $days[\count($days)-1]->format('Y-m-d');
    }
}
