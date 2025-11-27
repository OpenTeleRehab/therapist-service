<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('type')->default(User::TYPE_THERAPIST);
            $table->integer('region_id');
            $table->integer('province_id');
            $table->integer('phc_service_id')->nullable();
            $table->integer('clinic_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->dropColumn('region_id');
            $table->dropColumn('province_id');
            $table->dropColumn('phc_service_id');
            $table->integer('clinic_id')->change();
        });
    }
};
