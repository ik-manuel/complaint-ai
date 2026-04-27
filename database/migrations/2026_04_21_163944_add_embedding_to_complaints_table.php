<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable pgvector extension (safe to run multiple times)
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        // Add embedding column to complaints
        // 768 dimensions = nomic-embed-text output size
        DB::statement('ALTER TABLE complaints ADD COLUMN embedding vector(768)');

        // Add index for fast similarity search
        // ivfflat = approximate nearest neighbor (fast for large datasets)
        DB::statement('
            CREATE INDEX complaints_embedding_idx
            ON complaints
            USING ivfflat (embedding vector_cosine_ops)
            WITH (lists = 100)
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS complaints_embedding_idx');
        DB::statement('ALTER TABLE complaints DROP COLUMN IF EXISTS embedding');
    }
};
