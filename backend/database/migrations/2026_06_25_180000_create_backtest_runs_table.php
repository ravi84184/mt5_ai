<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('backtest_runs')) {
            return;
        }

        Schema::create('backtest_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('symbol', 32);
            $table->date('from_date');
            $table->date('to_date');
            $table->string('mode', 32)->default('rules');
            $table->string('status', 16)->default('PENDING');
            $table->json('params_json')->nullable();
            $table->json('results_json')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['symbol', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backtest_runs');
    }
};
