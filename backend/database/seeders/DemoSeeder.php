<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Athlete;
use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Models\FixedExpense;
use App\Models\Incident;
use App\Models\Measurement;
use App\Models\Practice;
use App\Models\Schedule;
use App\Models\TrainingGroup;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Venue;
use App\Services\FinanceService;
use App\Services\ScheduleService;
use Illuminate\Database\Seeder;

/**
 * Datos ficticios de demostración (Club Deportivo Marea Roja, natación).
 * Las fechas son relativas a "hoy" para que la demo siempre luzca vigente.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'Marea2026!';

    public function run(ScheduleService $schedules, FinanceService $finance): void
    {
        $today = now()->toImmutable();

        $u = [];
        foreach ([
            'admin' => ['Marcela Díaz', 'admin@marea.cl', 'admin', '+56 9 1111 1111'],
            'coach' => ['Andrés Rojas', 'entrenador@marea.cl', 'coach', '+56 9 2222 2222'],
            'coach2' => ['Paula Contreras', 'paula.contreras@marea.cl', 'coach', '+56 9 2222 3333'],
            'guardian' => ['Claudia Herrera', 'apoderado@marea.cl', 'guardian', '+56 9 3333 3333'],
            'guardian2' => ['Jorge Soto', 'jorge.soto@marea.cl', 'guardian', '+56 9 3333 4444'],
            'athlete' => ['Matías Herrera', 'alumno@marea.cl', 'athlete', null],
        ] as $key => [$name, $email, $role, $phone]) {
            $u[$key] = User::query()->create(['name' => $name, 'email' => $email, 'password' => self::PASSWORD, 'role' => $role, 'phone' => $phone, 'active' => true]);
        }

        $venues = collect([
            ['Piscina Olímpica Playa Ancha', 'Av. Playa Ancha 850, Valparaíso', true],
            ['Gimnasio Municipal Valparaíso', 'Av. Francia 1200, Valparaíso', true],
            ['Centro Acuático Estadio Nacional', 'Av. Grecia 2001, Ñuñoa, Santiago', false],
            ['Playa Laguna Verde', 'Laguna Verde, Valparaíso', false],
        ])->map(fn (array $v) => Venue::query()->create(['name' => $v[0], 'address' => $v[1], 'is_training_venue' => $v[2]]))->all();

        $groups = [
            'infantil' => TrainingGroup::query()->create(['name' => 'Infantil', 'description' => '8 a 11 años', 'level' => 'Formativo', 'coach_id' => $u['coach']->id, 'color' => '#0ea5e9']),
            'juvenil' => TrainingGroup::query()->create(['name' => 'Juvenil', 'description' => '12 a 17 años', 'level' => 'Competitivo', 'coach_id' => $u['coach2']->id, 'color' => '#8b5cf6']),
            'master' => TrainingGroup::query()->create(['name' => 'Máster', 'description' => 'Adultos', 'level' => 'Recreativo', 'coach_id' => $u['coach']->id, 'color' => '#f59e0b']),
        ];

        $a = [];
        foreach ([
            ['Matías', 'Herrera', '2013-04-12', 'juvenil', 'guardian', 'athlete', 35000],
            ['Sofía', 'Herrera', '2016-09-02', 'infantil', 'guardian', null, 30000],
            ['Benjamín', 'Soto', '2015-01-20', 'infantil', 'guardian2', null, 30000],
            ['Isidora', 'Morales', '2012-06-30', 'juvenil', null, null, 35000],
            ['Tomás', 'Fuentes', '2014-11-11', 'juvenil', null, null, 35000],
            ['Florencia', 'Pizarro', '2016-02-14', 'infantil', null, null, 30000],
            ['Vicente', 'Araya', '2017-08-08', 'infantil', null, null, 30000],
            ['Catalina', 'Reyes', '1988-03-03', 'master', null, null, 40000],
            ['Diego', 'Muñoz', '1979-12-01', 'master', null, null, 40000],
        ] as [$first, $last, $birth, $group, $guardian, $user, $fee]) {
            $a[] = Athlete::query()->create([
                'first_name' => $first, 'last_name' => $last, 'birth_date' => $birth,
                'group_id' => $groups[$group]->id, 'guardian_id' => $guardian ? $u[$guardian]->id : null,
                'user_id' => $user ? $u[$user]->id : null, 'monthly_fee' => $fee, 'active' => true,
            ])->id;
        }

        // Horarios y prácticas: desde hace 2 meses hasta dentro de 2 meses.
        $from = $today->subMonthsNoOverflow(2)->startOfMonth()->toDateString();
        $to = $today->addMonthsNoOverflow(2)->endOfMonth()->toDateString();
        foreach ([
            ['infantil', 'coach', 0, 1, '17:00', '18:30'],
            ['infantil', 'coach', 0, 3, '17:00', '18:30'],
            ['juvenil', 'coach2', 0, 2, '18:00', '20:00'],
            ['juvenil', 'coach2', 0, 4, '18:00', '20:00'],
            ['juvenil', 'coach', 1, 6, '09:00', '11:00'],
            ['master', 'coach', 0, 5, '19:30', '21:00'],
        ] as [$group, $coach, $venue, $weekday, $start, $end]) {
            $schedule = Schedule::query()->create([
                'group_id' => $groups[$group]->id, 'coach_id' => $u[$coach]->id, 'venue_id' => $venues[$venue]->id,
                'weekday' => $weekday, 'start_time' => $start, 'end_time' => $end, 'valid_from' => $from, 'active' => true,
            ]);
            $schedules->generate($schedule, $from, $to);
        }

        // Asistencia, objetivos y marcas en prácticas pasadas del grupo Juvenil.
        $past = Practice::query()->where('group_id', $groups['juvenil']->id)
            ->whereBetween('date', [$from, $today->subDay()->toDateString()])
            ->orderBy('date')->orderBy('start_time')->get();
        $juvenil = [$a[0], $a[3], $a[4]];
        foreach ($past as $i => $p) {
            $p->update(['status' => 'done', 'objectives' => $i % 2 === 0 ? 'Técnica de crol y virajes' : 'Series aeróbicas 10x100']);
            foreach ($juvenil as $j => $athleteId) {
                Attendance::query()->create(['practice_id' => $p->id, 'athlete_id' => $athleteId, 'rsvp' => 'confirmed', 'present' => ($i + $j) % 5 !== 0]);
            }
            if ($i % 3 === 0) {
                Measurement::query()->create([
                    'athlete_id' => $a[0], 'practice_id' => $p->id, 'metric' => '50 m libre', 'value' => round(34.5 - $i * 0.12, 2),
                    'unit' => 's', 'recorded_by' => $u['coach2']->id, 'recorded_at' => $p->date.' 18:30:00',
                ]);
                Measurement::query()->create([
                    'athlete_id' => $a[0], 'practice_id' => $p->id, 'metric' => '100 m espalda', 'value' => round(82.0 - $i * 0.2, 2),
                    'unit' => 's', 'recorded_by' => $u['coach2']->id, 'recorded_at' => $p->date.' 19:00:00',
                ]);
            }
        }
        if (isset($past[2])) {
            Incident::query()->create([
                'practice_id' => $past[2]->id, 'athlete_id' => $a[4], 'type' => 'injury', 'severity' => 'low',
                'description' => 'Molestia leve en hombro derecho; se indicó reposo de la sesión.', 'recorded_by' => $u['coach2']->id,
            ]);
        }

        // Finanzas: mensualidades de los últimos 3 meses, con algunos impagos.
        foreach ([
            ['Arriendo piscina', 'Arriendos', 90000, 5],
            ['Honorarios entrenadores', 'Remuneraciones', 180000, 28],
            ['Seguro deportivo', 'Seguros', 25000, 15],
        ] as [$name, $category, $amount, $day]) {
            FixedExpense::query()->create(['name' => $name, 'category' => $category, 'amount' => $amount, 'day_of_month' => $day, 'active' => true]);
        }
        for ($m = 2; $m >= 0; $m--) {
            $period = $today->subMonthsNoOverflow($m)->format('Y-m');
            $finance->generateCharges($period);
            $finance->applyFixedExpenses($period, $u['admin']->id);
            foreach ($finance->charges(['period' => $period])->values() as $k => $charge) {
                $unpaid = ($m === 0 && $k % 3 === 0) || ($m === 1 && $k === 2);
                if (! $unpaid) {
                    $finance->registerPayment([
                        'charge_id' => $charge->id, 'amount' => $charge->amount,
                        'paid_at' => $period.'-0'.(5 + $k % 4), 'method' => $k % 2 === 0 ? 'transfer' : 'cash',
                    ], $u['admin']->id);
                }
            }
        }
        Transaction::query()->create(['type' => 'income', 'category' => 'Auspicios', 'amount' => 500000, 'occurred_on' => $today->subMonthNoOverflow()->format('Y-m-12'), 'description' => 'Auspicio Ferretería El Puerto', 'created_by' => $u['admin']->id]);
        Transaction::query()->create(['type' => 'income', 'category' => 'Rifas y eventos', 'amount' => 180000, 'occurred_on' => $today->format('Y-m-03'), 'description' => 'Rifa aniversario', 'created_by' => $u['admin']->id]);
        Transaction::query()->create(['type' => 'expense', 'category' => 'Equipamiento', 'amount' => 125000, 'occurred_on' => $today->format('Y-m-02'), 'description' => 'Tablas y pull-buoys', 'created_by' => $u['admin']->id]);

        // Eventos en recintos distintos al de entrenamiento.
        foreach ([
            ['Campeonato Regional de Natación', 'competition', $venues[2]->id, null, $today->addWeeks(3)->next('Saturday'), '09:00', '18:00'],
            ['Travesía Laguna Verde', 'competition', $venues[3]->id, null, $today->addWeeks(6)->next('Sunday'), '10:00', '13:00'],
            ['Reunión de apoderados', 'meeting', null, 'Sede Club, Calle Blanco 1450, Valparaíso', $today->addDays(10), '19:00', '20:30'],
        ] as [$title, $type, $venue, $text, $day, $start, $end]) {
            $date = $day->toDateString();
            $event = Event::query()->create([
                'title' => $title, 'type' => $type, 'venue_id' => $venue, 'location_text' => $text,
                'start_at' => "{$date} {$start}:00", 'end_at' => "{$date} {$end}:00", 'created_by' => $u['admin']->id,
                'description' => 'Evento de demostración.',
            ]);
            foreach ($type === 'competition' ? [$a[0], $a[3], $a[4]] : [] as $athleteId) {
                EventParticipant::query()->create(['event_id' => $event->id, 'athlete_id' => $athleteId, 'rsvp' => 'pending']);
            }
        }
    }
}
