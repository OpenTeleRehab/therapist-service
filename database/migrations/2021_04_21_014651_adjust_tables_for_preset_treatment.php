<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AdjustTablesForPresetTreatment extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->dropColumn('description');
            $table->dropColumn('status');
        });

        Schema::dropIfExists('goals');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('treatment_plans', function (Blueprint $table) {
            $table->string('description');
            $table->string('status')->nullable();
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->integer('treatment_plan_id');
            $table->string('title');
            $table->string('frequency');
        });
    }
}
