<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('telegram_bot_token')->nullable()->after('settings');
            $table->string('telegram_bot_username')->nullable()->after('telegram_bot_token');
            $table->boolean('telegram_notifications_enabled')->default(false)->after('telegram_bot_username');
            $table->string('telegram_chat_id')->nullable()->after('telegram_notifications_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'telegram_bot_token',
                'telegram_bot_username', 
                'telegram_notifications_enabled',
                'telegram_chat_id'
            ]);
        });
    }
};
