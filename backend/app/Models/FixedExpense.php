<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $category
 * @property int $amount
 * @property int $day_of_month
 * @property bool $active
 */
class FixedExpense extends Model
{
    /** @var list<string> */
    protected $fillable = ['name', 'category', 'amount', 'day_of_month', 'active'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'day_of_month' => 'integer', 'active' => 'boolean'];
    }
}
