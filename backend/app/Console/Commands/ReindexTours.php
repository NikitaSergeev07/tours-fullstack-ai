<?php

namespace App\Console\Commands;

use App\Models\Tour;
use App\Services\Tours\TourIndexer;
use Illuminate\Console\Command;

class ReindexTours extends Command
{
    protected $signature = 'tours:reindex {--id=* : Reindex only specific tour IDs}';

    protected $description = 'Recompute embeddings for all tours (or a subset)';

    public function handle(TourIndexer $indexer): int
    {
        $query = Tour::query()->with('categories');
        if ($ids = $this->option('id')) {
            $query->whereIn('id', $ids);
        }
        $count = (clone $query)->count();
        $this->info("Reindexing {$count} tours...");

        $ok = 0;
        $query->chunkById(50, function ($tours) use ($indexer, &$ok) {
            foreach ($tours as $tour) {
                if ($indexer->index($tour)) {
                    $ok++;
                    $this->line("  ✓ {$tour->slug}");
                } else {
                    $this->warn("  ✗ {$tour->slug}");
                }
            }
        });

        $this->info("Done. Indexed: {$ok}/{$count}");
        return self::SUCCESS;
    }
}
