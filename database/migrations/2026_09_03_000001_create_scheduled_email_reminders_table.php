<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_email_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);
            $table->date('period_start');
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('item_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->string('failure_type')->nullable();
            $table->timestamps();
            $table->unique(['type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_email_reminders');
    }
};
