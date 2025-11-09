<?php
namespace Ad2210\MultiProjectTeamPlanning\Service;

use Ad2210\MultiProjectTeamPlanning\Options\PlannerOptions;
use DateInterval;
use DateTimeImmutable;

final class TimeGridBuilder
{
    public function __construct(private PlannerOptions $opt) {}

    /** @return array{rows:array<int,array{label:string, minute:int}>, start:string, end:string} */
    public function buildRows(string $mode): array
    {
        $g = $this->opt->gridFor($mode);

        [$whStart, $whEnd] = [$g['work_start'] ?? '07:00', $g['work_end'] ?? '19:00'];
        $overflow = (int)($g['overflow_hours'] ?? 2);
        $step = (int)($g['minute_step'] ?? 30);

        $start = DateTimeImmutable::createFromFormat('H:i', $whStart)->modify("-{$overflow} hours");
        $end   = DateTimeImmutable::createFromFormat('H:i', $whEnd)->modify("+{$overflow} hours");

        $timeFmtPhp = DateFormatter::icuToPhp($g['time_label_format'] ?? 'H:i');

        $rows = [];
        for ($t = $start; $t < $end; $t = $t->add(new DateInterval("PT{$step}M"))) {
            $rows[] = [
                'label'  => DateFormatter::formatIcu($t, $g['time_label_format'] ?? 'HH:mm', $this->opt->locale(), $this->opt->tz()),
                'minute' => ((int)$t->format('H')) * 60 + (int)$t->format('i'),
            ];
        }

        return [
            'rows'  => $rows,
            'start' => $start->format('H:i'),
            'end'   => $end->format('H:i'),
        ];
    }
}
