<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('messages')
            ->whereNotIn('therapist_id', function ($query) {
                $query->select('id')->from('users');
            })
            ->delete();

        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('therapist_id')->change();
            $table->foreign('therapist_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign('therapist_id');
            $table->integer('therapist_id')->change();
        });
    }
};
