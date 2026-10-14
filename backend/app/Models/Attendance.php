<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $practice_id
 * @property int $athlete_id
 * @property string $rsvp
 * @property bool|null $present
 * @property string|null $comment
 */
class Attendance extends Model
{
    /** @var string */
    protected $table = 'attendance';

    /** @var list<string> */
    protected $fillable = ['practice_id', 'athlete_id', 'rsvp', 'present', 'comment'];

    protected function casts(): array
    {
        return ['present' => 'boolean'];
    }
}
