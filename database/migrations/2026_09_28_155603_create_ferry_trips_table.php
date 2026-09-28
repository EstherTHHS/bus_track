<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ferry_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('trip_date');
            $table->enum('leg', ['morning', 'evening']);
            $table->time('trip_time')->nullable();
            $table->enum('status', ['taken', 'missed', 'cancelled'])->default('taken');
            $table->string('note')->nullable();
            $table->timestamps();

            // One morning + one evening per day, per user.
            // Also serves the "user's trips in this month" query.
            $table->unique(['user_id', 'trip_date', 'leg']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ferry_trips');
    }
};
