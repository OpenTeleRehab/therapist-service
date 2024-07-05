<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransfersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('patient_id');
            $table->unsignedInteger('from_therapist_id');
            $table->unsignedInteger('to_therapist_id');
            $table->unsignedInteger('clinic_id');
            $table->enum('therapist_type', ['lead', 'supplementary']);
            $table->enum('status', ['invited', 'declined']);
            $table->timestamps();

            $table->foreign('from_therapist_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transfers');
    }
}
