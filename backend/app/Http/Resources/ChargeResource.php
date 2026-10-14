<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Charge;
use App\Services\FinanceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Charge */
class ChargeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $daysLate = FinanceService::daysLate($this->status, (string) $this->due_date, now());
        $paid = $this->paidAmount();

        return [
            'id' => $this->id,
            'athlete_id' => $this->athlete_id,
            'period' => $this->period,
            'amount' => $this->amount,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'paid_amount' => $paid,
            'balance' => max(0, $this->amount - $paid),
            'days_late' => $daysLate,
            'is_overdue' => $daysLate > 0,
            'first_name' => $this->athlete->first_name,
            'last_name' => $this->athlete->last_name,
            'athlete_name' => $this->athlete->full_name,
            'guardian_id' => $this->athlete->guardian_id,
            'guardian_name' => $this->athlete->guardian?->name,
            'guardian_phone' => $this->athlete->guardian?->phone,
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'paid_at' => $p->paid_at,
                'method' => $p->method,
                'reference' => $p->reference,
            ])->values()),
        ];
    }
}
