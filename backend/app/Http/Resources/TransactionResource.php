<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transaction */
class TransactionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'category' => $this->category,
            'amount' => $this->amount,
            'occurred_on' => $this->occurred_on,
            'description' => $this->description,
            'payment_id' => $this->payment_id,
            'fixed_expense_id' => $this->fixed_expense_id,
            'period' => $this->period,
        ];
    }
}
