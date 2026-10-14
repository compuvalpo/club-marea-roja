<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\FixedExpense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FixedExpense */
class FixedExpenseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'amount' => $this->amount,
            'day_of_month' => $this->day_of_month,
            'active' => $this->active,
        ];
    }
}
