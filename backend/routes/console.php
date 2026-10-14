<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/*
| Comandos de mantenimiento del club.
*/

Artisan::command('jwt:secret {--show : Sólo muestra el secreto sin escribirlo}', function () {
    $secret = bin2hex(random_bytes(32));
    if ($this->option('show')) {
        $this->line($secret);

        return;
    }
    $path = base_path('.env');
    if (! is_file($path)) {
        $this->error('No existe el archivo .env (copie .env.example).');

        return 1;
    }
    $env = (string) file_get_contents($path);
    $env = preg_match('/^JWT_SECRET=.*$/m', $env)
        ? (string) preg_replace('/^JWT_SECRET=.*$/m', 'JWT_SECRET='.$secret, $env)
        : $env.PHP_EOL.'JWT_SECRET='.$secret.PHP_EOL;
    file_put_contents($path, $env);
    $this->info('JWT_SECRET generado.');
})->purpose('Genera un secreto aleatorio de 256 bits para firmar los JWT');

Artisan::command('club:create-admin {email} {name} {--password=}', function (string $email, string $name) {
    $password = $this->option('password') ?: $this->secret('Contraseña (mín. 8 caracteres)');
    if (! is_string($password) || Str::length($password) < 8) {
        $this->error('La contraseña debe tener al menos 8 caracteres.');

        return 1;
    }
    User::query()->create(['email' => mb_strtolower($email), 'name' => $name, 'password' => $password, 'role' => 'admin', 'active' => true]);
    $this->info("Administrador {$email} creado.");
})->purpose('Crea un usuario administrador');
