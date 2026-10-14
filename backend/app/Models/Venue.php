<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Recinto: lugar de entrenamiento habitual o sede externa de eventos.
 *
 * @property int $id
 * @property string $name
 * @property string|null $address
 * @property bool $is_training_venue
 */
class Venue extends Model
{
    /** @var list<string> */
    protected $fillable = ['name', 'address', 'is_training_venue', 'notes'];

    protected function casts(): array
    {
        return ['is_training_venue' => 'boolean'];
    }
}
