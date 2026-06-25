<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_interaction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('signal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('analysis_type', 32); // entry, position
            $table->string('provider', 32);
            $table->string('model', 64)->nullable();
            $table->string('symbol', 32)->nullable();
            $table->unsignedBigInteger('ticket')->nullable();
            $table->json('input_json');
            $table->text('system_prompt');
            $table->text('user_prompt');
            $table->json('output_json')->nullable();
            $table->string('status', 16); // success, error
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
            $table->index(['symbol', 'created_at']);
            $table->index('analysis_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interaction_logs');
    }
};
