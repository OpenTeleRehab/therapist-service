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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requester_id');
            $table->unsignedBigInteger('recipient_id');
            $table->timestamp('start_date');
            $table->timestamp('end_date');
            $table->enum('requester_status', ['accepted', 'rejected', 'invited', 'cancelled']);
            $table->enum('recipient_status', ['accepted', 'rejected', 'invited', 'cancelled']);
            $table->text('note')->nullable();
            $table->boolean('unread')->default(false);
            $table->timestamps();

            $table->foreign('requester_id')->references('id')->on('users');
            $table->foreign('recipient_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
