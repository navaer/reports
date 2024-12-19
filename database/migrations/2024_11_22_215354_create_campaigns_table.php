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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_name');
            $table->timestamp('call_start');
            $table->integer('hold_time');
            $table->integer('ring_time');
            $table->integer('talk_time');
            $table->string('transfer_destination')->nullable();
            $table->string('call_outcome_name');
            $table->string('contact_outcome_group');
            $table->integer('agent_id');
            $table->string('call_type');
            $table->string('ticket')->nullable();
            $table->string('call_outcome_group');
            $table->integer('queue_time');
            $table->uuid('call_uuid');
            $table->string('agent_first_name')->nullable();
            $table->integer('wait_time');
            $table->integer('wrap_up_time');
            $table->integer('call_length');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
