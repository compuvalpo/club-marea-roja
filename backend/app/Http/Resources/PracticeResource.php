<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Practice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Practice */
class PracticeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'schedule_id' => $this->schedule_id,
            'group_id' => $this->group_id,
            'coach_id' => $this->coach_id,
            'venue_id' => $this->venue_id,
            'date' => $this->date,
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => substr($this->end_time, 0, 5),
            'objectives' => $this->objectives,
            'notes' => $this->notes,
            'status' => $this->status,
            'group_name' => $this->group->name,
            'group_color' => $this->group->color,
            'coach_name' => $this->coach?->name,
            'venue_name' => $this->venue?->name,
            // Sólo para apoderados/alumnos: sus pupilos con su confirmación.
            'my_athletes' => $this->when($this->resource->getAttribute('my_athletes') !== null, fn () => $this->resource->getAttribute('my_athletes')),
        ];
    }
}
