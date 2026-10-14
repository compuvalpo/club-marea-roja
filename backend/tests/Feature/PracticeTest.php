<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class PracticeTest extends TestCase
{
    private const RANGE = '/api/v1/practices?from=2026-10-01&to=2026-10-31';

    public function test_coach_only_sees_assigned_practices(): void
    {
        $practices = $this->api('GET', self::RANGE, token: $this->coach())->json('data');

        self::assertNotEmpty($practices);
        self::assertSame([2], array_values(array_unique(array_column($practices, 'coach_id'))));
    }

    public function test_moving_a_practice_onto_an_occupied_slot_returns_a_friendly_conflict(): void
    {
        $token = $this->admin();
        $all = $this->api('GET', self::RANGE, token: $token)->json('data');
        $first = $all[0];
        $other = collect($all)->first(
            fn (array $p): bool => $p['group_id'] === $first['group_id']
                && $p['start_time'] === $first['start_time']
                && $p['date'] !== $first['date'],
        );
        self::assertNotNull($other, 'Se esperaba otra práctica del mismo grupo y horario.');

        $this->api('PATCH', '/api/v1/practices/'.$first['id'], ['date' => $other['date']], $token)
            ->assertStatus(409)
            ->assertJsonPath(
                'error.message',
                sprintf('El día %s ya cuenta con una programación para el horario %s.', date('d-m-Y', strtotime($other['date'])), substr((string) $first['start_time'], 0, 5)),
            );

        // Mantener la misma fecha y hora (sin cambios reales) no es un conflicto.
        $this->api('PATCH', '/api/v1/practices/'.$first['id'], ['date' => $first['date']], $token)->assertOk();
    }

    public function test_range_is_validated_and_limited(): void
    {
        $token = $this->admin();
        $this->api('GET', '/api/v1/practices', token: $token)->assertStatus(422);
        $this->api('GET', '/api/v1/practices?from=2026-01-01&to=2026-12-31', token: $token)->assertStatus(422);
    }

    public function test_coach_takes_attendance_and_sets_objectives(): void
    {
        $token = $this->coach();
        $practice = $this->todayInfantilPractice($token);

        $this->api('PATCH', '/api/v1/practices/'.$practice['id'], ['objectives' => 'Patada de crol con tabla'], $token)
            ->assertOk()->assertJsonPath('data.objectives', 'Patada de crol con tabla');

        $detail = $this->api('GET', '/api/v1/practices/'.$practice['id'], token: $token)->json('data');
        self::assertTrue($detail['can_manage']);
        $items = array_map(fn (array $a): array => ['athlete_id' => $a['athlete_id'], 'present' => true], $detail['attendance']);

        $saved = $this->api('PUT', '/api/v1/practices/'.$practice['id'].'/attendance', ['items' => $items], $token);
        $saved->assertOk();
        self::assertSame([true], array_values(array_unique($saved->json('data.*.present'))));
        $this->api('GET', '/api/v1/practices/'.$practice['id'], token: $token)->assertJsonPath('data.status', 'done');
    }

    public function test_coach_cannot_reassign_group_or_manage_others_practices(): void
    {
        $token = $this->coach();
        $practice = $this->todayInfantilPractice($token);
        $this->api('PATCH', '/api/v1/practices/'.$practice['id'], ['group_id' => 2], $token)->assertForbidden();

        // Práctica de martes del grupo Juvenil asignada a otra entrenadora.
        $other = $this->api('GET', self::RANGE.'&coach_id=3', token: $this->admin())->json('data.0');
        $this->api('PUT', '/api/v1/practices/'.$other['id'].'/attendance', ['items' => [['athlete_id' => 1, 'present' => true]]], $token)
            ->assertForbidden()
            ->assertJsonPath('error.message', 'Sólo el entrenador asignado puede gestionar esta práctica.');
    }

    public function test_coach_records_measurement_and_incident(): void
    {
        $token = $this->coach();
        $id = $this->todayInfantilPractice($token)['id'];

        $this->api('POST', "/api/v1/practices/{$id}/measurements", ['athlete_id' => 2, 'metric' => '25 m libre', 'value' => 21.4, 'unit' => 's'], $token)->assertCreated();
        $this->api('POST', "/api/v1/practices/{$id}/incidents", ['athlete_id' => 2, 'type' => 'injury', 'severity' => 'low', 'description' => 'Golpe leve en la mano al virar.'], $token)->assertCreated();
        // Alumno que no pertenece al grupo.
        $this->api('POST', "/api/v1/practices/{$id}/measurements", ['athlete_id' => 1, 'metric' => '25 m libre', 'value' => 20, 'unit' => 's'], $token)
            ->assertStatus(422)->assertJsonPath('error.errors.athlete_id.0', 'El alumno no pertenece al grupo de esta práctica.');

        $detail = $this->api('GET', "/api/v1/practices/{$id}", token: $token)->json('data');
        self::assertCount(1, $detail['measurements']);
        self::assertCount(1, $detail['incidents']);
    }

    public function test_guardian_confirms_and_cancels_child_practice(): void
    {
        $token = $this->guardian();
        $practice = $this->firstWhere($this->api('GET', self::RANGE, token: $token)->json('data'), fn (array $p): bool => $p['group_name'] === 'Infantil' && $p['date'] >= '2026-10-07');
        self::assertSame('pending', $practice['my_athletes'][0]['rsvp']);

        $this->api('POST', "/api/v1/practices/{$practice['id']}/rsvp", ['athlete_id' => 2, 'status' => 'cancelled'], $token)->assertOk();
        $after = $this->firstWhere($this->api('GET', self::RANGE, token: $token)->json('data'), fn (array $p): bool => $p['id'] === $practice['id']);
        self::assertSame('cancelled', $after['my_athletes'][0]['rsvp']);
    }

    public function test_guardian_cannot_rsvp_for_someone_elses_child(): void
    {
        $token = $this->guardian();
        $practice = $this->firstWhere($this->api('GET', self::RANGE, token: $token)->json('data'), fn (array $p): bool => $p['group_name'] === 'Infantil' && $p['date'] >= '2026-10-07');

        // Benjamín (id 3) es pupilo de otro apoderado.
        $this->api('POST', "/api/v1/practices/{$practice['id']}/rsvp", ['athlete_id' => 3, 'status' => 'confirmed'], $token)->assertForbidden();
    }

    public function test_cannot_rsvp_past_practice(): void
    {
        $token = $this->athlete();
        $past = $this->api('GET', '/api/v1/practices?from=2026-09-01&to=2026-09-30', token: $token)->json('data.0');

        $this->api('POST', "/api/v1/practices/{$past['id']}/rsvp", ['athlete_id' => 1, 'status' => 'confirmed'], $token)->assertStatus(409);
    }

    public function test_admin_generates_practices_from_schedule_idempotently(): void
    {
        $token = $this->admin();
        $id = $this->api('POST', '/api/v1/schedules', [
            'group_id' => 3, 'coach_id' => 2, 'venue_id' => 2, 'weekday' => 2,
            'start_time' => '07:00', 'end_time' => '08:00', 'valid_from' => '2026-10-01',
        ], $token)->assertCreated()->json('data.id');

        $this->api('POST', "/api/v1/schedules/{$id}/generate", ['from' => '2026-10-01', 'to' => '2026-10-31'], $token)
            ->assertJsonPath('data', ['created' => 4, 'skipped' => 0]);
        $this->api('POST', "/api/v1/schedules/{$id}/generate", ['from' => '2026-10-01', 'to' => '2026-10-31'], $token)
            ->assertJsonPath('data', ['created' => 0, 'skipped' => 4]);
    }

    public function test_schedule_rejects_end_before_start(): void
    {
        $this->api('POST', '/api/v1/schedules', [
            'group_id' => 1, 'weekday' => 1, 'start_time' => '18:00', 'end_time' => '17:00', 'valid_from' => '2026-10-01',
        ], $this->admin())->assertStatus(422)->assertJsonPath('error.errors.end_time.0', 'La hora de término debe ser posterior a la de inicio.');
    }

    public function test_admin_cannot_create_duplicated_practice(): void
    {
        $this->api('POST', '/api/v1/practices', [
            'group_id' => 1, 'date' => '2026-10-05', 'start_time' => '17:00', 'end_time' => '18:30',
        ], $this->admin())->assertStatus(409);
    }

    /**
     * @return array<string, mixed>
     */
    private function todayInfantilPractice(string $token): array
    {
        return $this->firstWhere($this->api('GET', '/api/v1/practices?from=2026-10-05&to=2026-10-05', token: $token)->json('data'), fn (array $p): bool => $p['group_name'] === 'Infantil');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function firstWhere(array $items, callable $predicate): array
    {
        foreach ($items as $item) {
            if ($predicate($item)) {
                return $item;
            }
        }
        self::fail('No se encontró el elemento esperado.');
    }
}
