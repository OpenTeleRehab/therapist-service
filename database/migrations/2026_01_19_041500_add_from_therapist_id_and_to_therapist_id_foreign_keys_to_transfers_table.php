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
        DB::table('transfers')
            ->whereNotIn('from_therapist_id', function ($query) {
                $query->select('id')->from('users');
            })
            ->orWhereNotIn('to_therapist_id', function ($query) {
                $query->select('id')->from('users');
            })
            ->delete();

        Schema::table('transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('from_therapist_id')->change();
            $table->unsignedBigInteger('to_therapist_id')->change();
            $table->foreign('from_therapist_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('to_therapist_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['from_therapist_id']);
            $table->dropForeign(['to_therapist_id']);
            $table->unsignedInteger('from_therapist_id')->change();
            $table->unsignedInteger('to_therapist_id')->change();
        });
    }
};
