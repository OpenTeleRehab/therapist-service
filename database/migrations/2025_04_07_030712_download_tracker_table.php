<?php

use App\Enums\ExportStatus;
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
        Schema::create('download_trackers', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('job_id');
            $table->enum('status', [ExportStatus::IN_PROGRESS->value, ExportStatus::FAILED->value, ExportStatus::SUCCESS->value])->default(ExportStatus::IN_PROGRESS->value);
            $table->text('file_path')->nullable();
            $table->bigInteger('author_id')->unsigned();
            $table->timestamps();

            $table->foreign('author_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('download_trackers');
    }
};
