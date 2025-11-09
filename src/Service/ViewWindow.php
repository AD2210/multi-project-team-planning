<?php
namespace Ad2210\MultiProjectTeamPlanning\Service;

use Ad2210\MultiProjectTeamPlanning\Enum\Period;
use DateInterval; use DateTimeImmutable;

final class ViewWindow
{
    /** @return array{start:DateTimeImmutable,end:DateTimeImmutable,days:DateTimeImmutable[]} */
    public function compute(DateTimeImmutable $anchor, Period $period): array
    {
        $anchor = $anchor->setTime(0,0);
        switch ($period) {
            case Period::Day:
                $start = $anchor; $end = $anchor->modify('+1 day');
                $days = [$anchor];
                break;
            case Period::Workweek:
            case Period::Week:
                $monday = $anchor->modify('monday this week');
                $len = $period === Period::Workweek ? 5 : 7;
                $start = $monday; $end = $monday->modify("+{$len} days");
                $days = array_map(fn($i)=>$monday->modify("+{$i} days"), range(0,$len-1));
                break;
            case Period::Month:
            default:
                $start = $anchor->modify('first day of this month');
                $end   = $start->modify('first day of next month');
                $days=[]; for($d=$start; $d<$end; $d=$d->add(new DateInterval('P1D'))) $days[]=$d;
                break;
        }
        return ['start'=>$start,'end'=>$end,'days'=>$days];
    }
}
