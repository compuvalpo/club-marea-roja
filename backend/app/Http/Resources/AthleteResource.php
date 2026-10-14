<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Athlete;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Athlete */
class AthleteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'guardian_id' => $this->guardian_id,
            'group_id' => $this->group_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'birth_date' => $this->birth_date,
            'monthly_fee' => $this->monthly_fee,
            'active' => $this->active,
            'group_name' => $this->group?->name,
            'guardian_name' => $this->guardian?->name,
            'guardian_email' => $this->guardian?->email,
        ];
    }
}
