<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Montos en pesos chilenos (CLP) como enteros.
        Schema::create('charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('athlete_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7); // AAAA-MM
            $table->unsignedInteger('amount');
            $table->date('due_date');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['athlete_id', 'period']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charge_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->date('paid_at');
            $table->string('method', 20);
            $table->string('reference', 120)->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fixed_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('category', 60);
            $table->unsignedInteger('amount');
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10); // income | expense
            $table->string('category', 60);
            $table->unsignedInteger('amount');
            $table->date('occurred_on')->index();
            $table->string('description')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('fixed_expense_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period', 7)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('fixed_expenses');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('charges');
    }
};
