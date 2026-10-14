<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Practice;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Genera prácticas concretas a partir de horarios semanales recurrentes.
 */
class ScheduleService
{
    public const MAX_RANGE_DAYS = 180;

    /**
     * Fechas (Y-m-d) en que ocurre un día ISO (1 = lunes … 7 = domingo) dentro del rango y la vigencia.
     *
     * @return list<string>
     */
    public static function occurrences(int $weekday, string $from, string $to, ?string $validFrom = null, ?string $validTo = null): array
    {
        if ($weekday < 1 || $weekday > 7) {
            throw new \InvalidArgumentException('weekday debe estar entre 1 y 7');
        }
        $start = CarbonImmutable::parse(max($from, $validFrom ?? $from));
        $end = CarbonImmutable::parse(min($to, $validTo ?? $to));
        if ($start->greaterThan($end)) {
            return [];
        }

        $current = $start->addDays(($weekday - $start->dayOfWeekIso + 7) % 7);
        $dates = [];
        while ($current->lessThanOrEqualTo($end)) {
            $dates[] = $current->toDateString();
            $current = $current->addWeek();
        }

        return $dates;
    }

    /**
     * Idempotente: omite fechas que ya tienen práctica del grupo a la misma hora.
     *
     * @return array{created: int, skipped: int}
     */
    public function generate(Schedule $schedule, string $from, string $to): array
    {
        if ($from > $to || CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to)) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => [sprintf('El rango debe ser válido y de máximo %d días.', self::MAX_RANGE_DAYS)]]);
        }

        $created = 0;
        $skipped = 0;
        $dates = self::occurrences($schedule->weekday, $from, $to, (string) $schedule->valid_from, $schedule->valid_to);

        foreach ($dates as $date) {
            $exists = Practice::query()
                ->where('group_id', $schedule->group_id)
                ->where('date', $date)
                ->where('start_time', $schedule->start_time)
                ->exists();
            if ($exists) {
                $skipped++;

                continue;
            }
            Practice::query()->create([
                'schedule_id' => $schedule->id,
                'group_id' => $schedule->group_id,
                'coach_id' => $schedule->coach_id,
                'venue_id' => $schedule->venue_id,
                'date' => $date,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'status' => 'scheduled',
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
