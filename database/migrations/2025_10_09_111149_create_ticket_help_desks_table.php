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
        Schema::create('ticket_help_desks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('ticket_id')->nullable();
            $table->string('transaction_id')->nullable();
            $table->enum('status', ['open', 'in_progress', 'resolved', 'closed'])->default('open')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('low')->nullable();
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->string('attachment')->nullable();
            $table->json('admin_reply')->nullable();
            $table->string('assigned_to')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_help_desks');
    }
};
