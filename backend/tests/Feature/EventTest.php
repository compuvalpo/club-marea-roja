<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class EventTest extends TestCase
{
    public function test_admin_creates_event_at_external_venue(): void
    {
        $token = $this->admin();
        $venue = $this->api('POST', '/api/v1/venues', ['name' => 'Piscina Temperada Viña del Mar', 'address' => 'Av. Libertad 1500'], $token)->json('data');

        $this->api('POST', '/api/v1/events', [
            'title' => 'Amistoso interclubes', 'type' => 'competition', 'venue_id' => $venue['id'],
            'start_at' => '2026-11-07 09:00', 'end_at' => '2026-11-07 13:00',
        ], $token)->assertCreated()->assertJsonPath('data.location', 'Piscina Temperada Viña del Mar');
    }

    public function test_event_requires_venue_or_free_text_location(): void
    {
        $this->api('POST', '/api/v1/events', ['title' => 'Sin lugar', 'type' => 'social', 'start_at' => '2026-11-07 09:00', 'end_at' => '2026-11-07 10:00'], $this->admin())
            ->assertStatus(422)->assertJsonStructure(['error' => ['errors' => ['venue_id']]]);
    }

    public function test_event_end_must_be_after_start(): void
    {
        $this->api('POST', '/api/v1/events', [
            'title' => 'Al revés', 'type' => 'social', 'location_text' => 'Sede', 'start_at' => '2026-11-07 10:00', 'end_at' => '2026-11-07 09:00',
        ], $this->admin())->assertStatus(422);
    }

    public function test_guardian_confirms_summoned_child_and_only_sees_own_participants(): void
    {
        $token = $this->guardian();
        $competition = collect($this->api('GET', '/api/v1/events', token: $token)->json('data'))->firstWhere('type', 'competition');

        $detail = $this->api('GET', '/api/v1/events/'.$competition['id'], token: $token)->json('data');
        self::assertSame([1], array_column($detail['participants'], 'athlete_id'));

        $this->api('POST', "/api/v1/events/{$competition['id']}/rsvp", ['athlete_id' => 1, 'status' => 'confirmed'], $token)->assertOk();
        // Sofía (id 2) no está convocada.
        $this->api('POST', "/api/v1/events/{$competition['id']}/rsvp", ['athlete_id' => 2, 'status' => 'confirmed'], $token)->assertStatus(422);
    }
}
