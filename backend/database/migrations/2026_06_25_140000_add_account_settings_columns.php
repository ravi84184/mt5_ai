<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('ai_provider', 32)->nullable()->after('broker');
            $table->json('symbols')->nullable()->after('ai_provider');
            $table->boolean('trading_enabled')->default(true)->after('symbols');
            $table->unsignedTinyInteger('min_confidence')->nullable()->after('trading_enabled');
            $table->unsignedTinyInteger('max_open_trades')->nullable()->after('min_confidence');
            $table->text('admin_notes')->nullable()->after('max_open_trades');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn([
                'ai_provider',
                'symbols',
                'trading_enabled',
                'min_confidence',
                'max_open_trades',
                'admin_notes',
            ]);
        });
    }
};
