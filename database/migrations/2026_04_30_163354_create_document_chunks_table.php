<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                  ->constrained('documents')
                  ->onDelete('cascade');
            $table->integer('chunk_index'); // position in document
            $table->integer('page_number')->nullable();
            $table->text('content'); // raw chunk text
            $table->integer('token_count')->default(0);
            $table->timestamps();
        });

        // Add vector column separately (pgvector syntax)
        DB::statement('ALTER TABLE document_chunks ADD COLUMN embedding vector(768)');

        // Add index for fast similarity search
        DB::statement('
            CREATE INDEX document_chunks_embedding_idx 
            ON document_chunks
            USING ivfflat (embedding vector_cosine_ops)
            WITH (lists = 100)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
