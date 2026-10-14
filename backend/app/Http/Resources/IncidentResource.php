<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Incident */
class IncidentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'practice_id' => $this->practice_id,
            'athlete_id' => $this->athlete_id,
            'type' => $this->type,
            'severity' => $this->severity,
            'description' => $this->description,
            'created_at' => $this->created_at?->toDateTimeString(),
            'athlete_name' => $this->athlete?->full_name,
            'recorded_by_name' => $this->recorder?->name,
        ];
    }
}
