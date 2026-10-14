<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Refresh token opaco (sólo se guarda su hash SHA-256). Pertenece a una sesión y se rota en cada uso.
 *
 * @property int $id
 * @property string $session_id
 * @property int $user_id
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property bool $revoked
 * @property Carbon|null $revoked_at
 * @property-read AuthSession|null $session
 * @property-read User|null $user
 */
class RefreshToken extends Model
{
    /** @var list<string> */
    protected $fillable = ['session_id', 'user_id', 'token_hash', 'expires_at', 'revoked', 'revoked_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked' => 'boolean', 'revoked_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AuthSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AuthSession::class, 'session_id');
    }
}
