<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Log;

class ImportChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public int $importId;
    public int $offset;
    public int $limit;
    public int $startLine;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $importId, int $offset, int $limit, int $startLine)
    {
        $this->importId = $importId;
        $this->offset = $offset;
        $this->limit = $limit;
        $this->startLine = $startLine;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('import', [
            'offset' => $this->offset,
            'limit' => $this->limit,
            'startLine' => $this->startLine
            ]);
    }
}
