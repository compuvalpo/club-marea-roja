<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('training_groups')->cascadeOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->unsignedTinyInteger('weekday'); // 1 = lunes … 7 = domingo (ISO-8601)
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('practices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->nullable()->constrained('schedules')->nullOnDelete();
            $table->foreignId('group_id')->constrained('training_groups')->cascadeOnDelete();
            $table->foreignId('coach_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->date('date')->index();
            $table->string('start_time', 5);
            $table->string('end_time', 5);
            $table->text('objectives')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();
            $table->unique(['group_id', 'date', 'start_time']);
        });

        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->string('rsvp', 20)->default('pending');
            $table->boolean('present')->nullable();
            $table->string('comment')->nullable();
            $table->timestamps();
            $table->unique(['practice_id', 'athlete_id']);
        });

        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('metric', 80);
            $table->decimal('value', 12, 3);
            $table->string('unit', 20);
            $table->string('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('recorded_at');
            $table->timestamps();
        });

        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('athlete_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('severity', 10)->default('low');
            $table->string('description', 1000);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('measurements');
        Schema::dropIfExists('attendance');
        Schema::dropIfExists('practices');
        Schema::dropIfExists('schedules');
    }
};
