<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('api_token_hash', 64)->nullable()->unique()->after('broker');
            $table->timestamp('api_token_created_at')->nullable()->after('api_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['api_token_hash', 'api_token_created_at']);
        });
    }
};
