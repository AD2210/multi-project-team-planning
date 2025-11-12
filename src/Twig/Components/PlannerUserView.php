<?php
namespace Ad2210\MultiProjectTeamPlanning\Twig\Components;

use Ad2210\MultiProjectTeamPlanning\Enum\Period;
use Ad2210\MultiProjectTeamPlanning\Options\PlannerOptions;
use Ad2210\MultiProjectTeamPlanning\Service\DateFormatter;
use Ad2210\MultiProjectTeamPlanning\Service\TimeGridBuilder;
use Ad2210\MultiProjectTeamPlanning\Service\ViewWindow;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
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
                'ymd'   => $d->format('Y-m-d'),
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
    public function setAnchor(string $value): void
    {
        $this->anchor = $value;
    }

    #[LiveAction]
    public function next(): void
    {
        $this->anchor = $this->shiftAnchor(+1);
    }

    #[LiveAction]
    public function prev(): void
    {
        $this->anchor = $this->shiftAnchor(-1);
    }

    #[LiveAction]
    public function setPeriod(#[LiveArg] string $period): void {
        $this->period = $period;
        $this->anchor = 'today'; // reset anchor, voir si on le conserve
    }

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

    private function shiftAnchor(int $offset): string
    {
        $tz = new \DateTimeZone($this->opt->tz());

        $ref = $this->anchor === 'today'
            ? new \DateTimeImmutable('now', $tz)
            : \DateTimeImmutable::createFromFormat('Y-m-d', $this->anchor, $tz);

        if (!$ref) {
            $ref = new \DateTimeImmutable('now', $tz);
        }

        return match ($this->period) {
            'month'     => $ref->modify("$offset month")->format('Y-m-d'),
            'week',
            'workweek'  => $ref->modify("$offset week")->format('Y-m-d'),
            default     => $ref->modify("$offset day")->format('Y-m-d'),
        };
    }
}
