<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mt5_login')->unique();
            $table->string('broker')->nullable();
            $table->decimal('balance', 18, 2)->default(0);
            $table->decimal('equity', 18, 2)->default(0);
            $table->decimal('free_margin', 18, 2)->default(0);
            $table->decimal('daily_pnl', 18, 2)->default(0);
            $table->date('pnl_date')->nullable();
            $table->timestamps();
        });

        Schema::create('signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 32);
            $table->string('action', 16);
            $table->decimal('entry_price', 18, 8)->nullable();
            $table->decimal('stop_loss', 18, 8)->nullable();
            $table->decimal('take_profit', 18, 8)->nullable();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->text('reason')->nullable();
            $table->string('status', 16)->default('PENDING');
            $table->unsignedBigInteger('ticket')->nullable();
            $table->string('ai_provider', 32)->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'symbol', 'status']);
        });

        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket')->unique();
            $table->foreignId('signal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 32);
            $table->string('type', 8);
            $table->decimal('lot', 10, 4)->default(0);
            $table->decimal('entry_price', 18, 8);
            $table->decimal('close_price', 18, 8)->nullable();
            $table->decimal('profit', 18, 2)->default(0);
            $table->string('status', 16)->default('OPEN');
            $table->timestamps();

            $table->index(['account_id', 'status']);
        });

        Schema::create('trade_management_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket');
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('action', 32);
            $table->decimal('old_sl', 18, 8)->nullable();
            $table->decimal('new_sl', 18, 8)->nullable();
            $table->decimal('close_volume', 10, 4)->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 16)->default('PENDING');
            $table->timestamps();

            $table->index(['ticket', 'status']);
        });

        Schema::create('market_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 32);
            $table->string('timeframe', 8);
            $table->json('snapshot_json');
            $table->timestamps();

            $table->index(['account_id', 'symbol', 'created_at']);
        });

        Schema::create('position_management_decisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket');
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('action', 32);
            $table->decimal('new_sl', 18, 8)->nullable();
            $table->decimal('close_volume', 10, 4)->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 16)->default('PENDING');
            $table->timestamps();

            $table->index(['ticket', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_management_decisions');
        Schema::dropIfExists('market_snapshots');
        Schema::dropIfExists('trade_management_logs');
        Schema::dropIfExists('trades');
        Schema::dropIfExists('signals');
        Schema::dropIfExists('accounts');
    }
};
