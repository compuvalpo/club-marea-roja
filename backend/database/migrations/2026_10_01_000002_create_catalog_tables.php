<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('address')->nullable();
            $table->boolean('is_training_venue')->default(false);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('training_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('description')->nullable();
            $table->string('level', 40)->nullable();
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('color', 7)->default('#2563eb');
            $table->timestamps();
        });

        Schema::create('athletes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('guardian_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('training_groups')->nullOnDelete();
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->date('birth_date')->nullable();
            $table->unsignedInteger('monthly_fee')->default(0); // CLP
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athletes');
        Schema::dropIfExists('training_groups');
        Schema::dropIfExists('venues');
    }
};
