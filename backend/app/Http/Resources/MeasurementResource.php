<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Measurement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Measurement */
class MeasurementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'athlete_id' => $this->athlete_id,
            'practice_id' => $this->practice_id,
            'metric' => $this->metric,
            'value' => $this->value,
            'unit' => $this->unit,
            'notes' => $this->notes,
            'recorded_at' => $this->recorded_at->toDateTimeString(),
            'athlete_name' => $this->athlete?->full_name,
        ];
    }
}
