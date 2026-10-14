<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Schedule */
class ScheduleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'coach_id' => $this->coach_id,
            'venue_id' => $this->venue_id,
            'weekday' => $this->weekday,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'valid_from' => $this->valid_from,
            'valid_to' => $this->valid_to,
            'active' => $this->active,
            'group_name' => $this->group?->name,
            'coach_name' => $this->coach?->name,
            'venue_name' => $this->venue?->name,
        ];
    }
}
