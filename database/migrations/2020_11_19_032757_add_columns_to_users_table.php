<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddColumnsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('first_name');
            $table->string('last_name');
            $table->integer('clinic_id');
            $table->integer('country_id');
            $table->integer('limit_patient');
            $table->integer('language_id')->nullable();
            $table->integer('profession_id')->nullable();
            $table->integer('identity')->unique()->nullable();
            $table->boolean('enabled')->default(0);
        });

        DB::statement('ALTER TABLE users CHANGE identity identity INT(9) UNSIGNED ZEROFILL');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name');
            $table->dropColumn('first_name');
            $table->dropColumn('last_name');
            $table->dropColumn('clinic_id');
            $table->dropColumn('country_id');
            $table->dropColumn('language_id');
            $table->dropColumn('profession_id');
            $table->dropColumn('limit_patient');
            $table->dropColumn('enabled');
        });
    }
}
