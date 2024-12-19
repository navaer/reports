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
        Schema::create('agents_event_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_subtype_name')->nullable(); // Event Subtype Name
            $table->string('event_type')->nullable(); // Event Type
            $table->unsignedBigInteger('user_id'); // User Id
            $table->timestamp('event_date')->nullable(); // Event Date
            $table->string('user_full_name')->nullable(); // User Full Name
            $table->string('username')->nullable(); // Username
            $table->string('event_subtype')->nullable(); // Event Subtype
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents_event_logs');
    }
};
