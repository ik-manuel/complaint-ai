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
        Schema::create('token_usage_logs', function (Blueprint $table) {
            $table->id();
            // What triggered this API call
            $table->string('operation');
            // e.g. 'classification', 'response_generation',
            //      'conversation_turn', 'rag_answer', 'tool_call',
            //      'summarization', 'embedding'

            // Link to complaint if applicable
            $table->foreignId('complaint_id')
                  ->nullable()
                  ->constrained('complaints')
                  ->onDelete('set null');

            // Link to conversation if applicable
            $table->foreignId('conversation_id')
                  ->nullable()
                  ->constrained('conversations')
                  ->onDelete('set null');

            // Token breakdown
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);

            // Cost in USD (calculated at log time)
            $table->decimal('cost_usd', 10, 8)->default(0);

            // Model used
            $table->string('model')->default('llama-3.1-70b-versatile');

            // Additional context
            $table->json('metadata')->nullable();

            $table->timestamps();

            // Indexes for dashboard queries
            $table->index('operation');
            $table->index('complaint_id');
            $table->index('conversation_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_usage_logs');
    }
};
