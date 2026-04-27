<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Complaint;
use App\Services\EmbeddingService;
use Illuminate\Support\Facades\DB;

class GenerateComplaintEmbeddings extends Command
{
    protected $signature   = 'complaints:embed';
    protected $description = 'Generate embeddings for complaints that do not have one yet';

    public function handle(EmbeddingService $embeddingService): void
    {
        // Only process complaints without embeddings
        $complaints = Complaint::whereNull('embedding')->get();

        if ($complaints->isEmpty()) {
            $this->info('All complaints already have embeddings.');
            return;
        }

        $this->info("Generating embeddings for {$complaints->count()} complaints...");
        $bar = $this->output->createProgressBar($complaints->count());
        $bar->start();

        $success = 0;
        $failed  = 0;

        foreach ($complaints as $complaint) {
            try {
                $text      = $embeddingService->buildComplaintText($complaint);
                $embedding = $embeddingService->embed($text);

                DB::table('complaints')
                    ->where('id', $complaint->id)
                    ->update([
                        'embedding' => $embeddingService->formatForStorage($embedding)
                    ]);

                $success++;

            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed for complaint {$complaint->id}: {$e->getMessage()}");
                $failed++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Done! Success: {$success}, Failed: {$failed}");
    }
}