<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Event */
class EventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'venue_id' => $this->venue_id,
            'location_text' => $this->location_text,
            'start_at' => substr((string) $this->start_at, 0, 19),
            'end_at' => substr((string) $this->end_at, 0, 19),
            'venue_name' => $this->venue?->name,
            'venue_address' => $this->venue?->address,
            'location' => $this->venue->name ?? $this->location_text,
            'participants' => $this->when($this->resource->getAttribute('visible_participants') !== null, fn () => $this->resource->getAttribute('visible_participants')),
        ];
    }
}
