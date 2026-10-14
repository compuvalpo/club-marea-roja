<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TrainingGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TrainingGroup */
class GroupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'level' => $this->level,
            'coach_id' => $this->coach_id,
            'color' => $this->color,
            'coach_name' => $this->coach?->name,
            'athletes_count' => (int) ($this->athletes_count ?? 0),
        ];
    }
}
