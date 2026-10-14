<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $amount
 */
class Payment extends Model
{
    /** @var list<string> */
    protected $fillable = ['charge_id', 'amount', 'paid_at', 'method', 'reference', 'registered_by'];

    public const METHODS = ['cash', 'transfer', 'card', 'other'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}
