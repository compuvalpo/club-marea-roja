<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesiones revocables en servidor.
 *
 * Cada inicio de sesión crea una fila en "auth_sessions"; el access token (JWT) lleva su id en el claim "sid"
 * y la API comprueba en cada petición que la sesión siga vigente. Así un logout, un cambio de rol o la
 * desactivación de un usuario invalidan de inmediato sus tokens, sin esperar a que expiren.
 *
 * Los refresh tokens pasan a pertenecer a una sesión (familia de rotación). Se recrea la tabla porque sólo
 * contiene datos efímeros (todas las sesiones previas quedan cerradas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_sessions', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('last_activity_at');
            $table->dateTime('expires_at');
            $table->dateTime('revoked_at')->nullable();
            $table->string('revoked_reason', 40)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at']);
        });

        Schema::dropIfExists('refresh_tokens');
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64);
            $table->foreign('session_id')->references('id')->on('auth_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->boolean('revoked')->default(false);
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
        Schema::dropIfExists('auth_sessions');

        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->boolean('revoked')->default(false);
            $table->timestamps();
        });
    }
};
