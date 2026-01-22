<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_email')->default(1);
            $table->boolean('notify_in_app')->default(0);
        });

        /**
         * Assign notifiable to users type clinic_admin and phc_service_admin
         */
        DB::table('users')->whereNotNull('phc_service_id')->update(['notify_in_app' =>  1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notify_email');
            $table->dropColumn('notify_in_app');
        });
    }
};
