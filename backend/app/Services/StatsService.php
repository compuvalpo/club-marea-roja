<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Resources\IncidentResource;
use App\Models\Athlete;
use App\Models\Incident;
use App\Models\Measurement;
use Illuminate\Support\Facades\DB;

/**
 * Estadísticas deportivas por alumno: asistencia, marcas por métrica e incidencias.
 */
class StatsService
{
    /**
     * @return array<string, mixed>
     */
    public function forAthlete(Athlete $athlete): array
    {
        $row = DB::table('practices as p')
            ->leftJoin('attendance as at', function ($join) use ($athlete): void {
                $join->on('at.practice_id', '=', 'p.id')->where('at.athlete_id', '=', $athlete->id);
            })
            ->where('p.group_id', $athlete->group_id)
            ->where('p.date', '<=', today()->toDateString())
            ->where('p.status', '<>', 'cancelled')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN at.present = 1 THEN 1 ELSE 0 END) AS attended')
            ->selectRaw("SUM(CASE WHEN at.rsvp = 'confirmed' THEN 1 ELSE 0 END) AS confirmed")
            ->selectRaw("SUM(CASE WHEN at.rsvp = 'cancelled' THEN 1 ELSE 0 END) AS cancelled")
            ->first();

        $attendance = [
            'total' => (int) ($row->total ?? 0),
            'attended' => (int) ($row->attended ?? 0),
            'confirmed' => (int) ($row->confirmed ?? 0),
            'cancelled' => (int) ($row->cancelled ?? 0),
        ];
        $attendance['rate'] = $attendance['total'] > 0 ? round($attendance['attended'] / $attendance['total'] * 100, 1) : null;

        $measurements = Measurement::query()->where('athlete_id', $athlete->id)->orderBy('recorded_at')->get()
            ->map(fn (Measurement $m): array => [
                'metric' => $m->metric,
                'unit' => $m->unit,
                'value' => $m->value,
                'recorded_at' => $m->recorded_at->toDateTimeString(),
            ])->all();

        $incidents = Incident::query()->with(['athlete', 'recorder'])->where('athlete_id', $athlete->id)->latest()->get();

        return [
            'attendance' => $attendance,
            'metrics' => self::groupMetrics($measurements),
            'incidents' => IncidentResource::collection($incidents)->resolve(),
        ];
    }

    /**
     * Agrupa marcas por métrica. Mejor marca: mínimo para tiempos (s, seg, min, ms), máximo para el resto.
     *
     * @param  array<int, array<string, mixed>>  $measurements
     * @return list<array{metric: string, unit: string, best: float, last: float, count: int, series: list<array{date: string, value: float}>}>
     */
    public static function groupMetrics(array $measurements): array
    {
        $grouped = [];
        foreach ($measurements as $m) {
            $grouped[(string) $m['metric']][] = $m;
        }

        $result = [];
        foreach ($grouped as $metric => $items) {
            $unit = (string) $items[0]['unit'];
            $values = array_map(static fn (array $i): float => (float) $i['value'], $items);
            $lowerIsBetter = in_array($unit, ['s', 'seg', 'min', 'ms'], true);
            $result[] = [
                'metric' => (string) $metric,
                'unit' => $unit,
                'best' => $lowerIsBetter ? min($values) : max($values),
                'last' => $values[array_key_last($values)],
                'count' => count($values),
                'series' => array_map(static fn (array $i): array => [
                    'date' => substr((string) $i['recorded_at'], 0, 10),
                    'value' => (float) $i['value'],
                ], $items),
            ];
        }

        return $result;
    }
}
