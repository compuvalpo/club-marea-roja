<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Movimiento contable (ingreso o egreso).
 *
 * @property int $id
 * @property string $type
 * @property string $category
 * @property int $amount
 * @property string $occurred_on
 * @property int|null $payment_id
 */
class Transaction extends Model
{
    /** @var list<string> */
    protected $fillable = ['type', 'category', 'amount', 'occurred_on', 'description', 'payment_id', 'fixed_expense_id', 'period', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }
}
