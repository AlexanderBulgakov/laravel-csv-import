<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Import;
use App\Imports\CsvLeadHeader;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Throwable;

class PrepareImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Import $import;

    public const CHUNK_SIZE = 1000;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Import $import)
    {
        $this->import = $import;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $start = microtime(true);
        Log::info('prepare start', ['id' => $this->import->id]);

        $this->import->status = Import::STATUS_PROCESSING;
        $this->import->save();

        $handle = fopen(Storage::path($this->import->path), 'r');

        if ($handle === false) {
            $errosMessage = 'Failed to open CSV';

            $this->import->status = Import::STATUS_FAILED;
            $this->import->error_message = $errosMessage;
            $this->import->save();

            $this->fail(new RuntimeException($errosMessage));
            return;
        }

        $header = fgetcsv($handle, 0, ',');
        $header[0] = str_replace("\xEF\xBB\xBF", '', $header[0]);

        if (CsvLeadHeader::compare($header) === false) {
            $errosMessage = 'Invalid header in CSV file';

            $this->import->status = Import::STATUS_FAILED;
            $this->import->error_message = $errosMessage;
            $this->import->save();

            $this->fail(new RuntimeException($errosMessage));
            return;
        }

        try {
            $startLine = 2;
            $offset = ftell($handle);
            $count = 0;
            $limit = self::CHUNK_SIZE;
            $totalRows = 0;
            $jobs = [];

            while (fgetcsv($handle) !== false) {
                $count++;
                $totalRows++;

                if ($count === $limit) {
                    $jobs[] = new ImportChunkJob(
                        $this->import->id,
                        $offset,
                        $limit,
                        $startLine
                    );

                    $offset = ftell($handle);
                    $startLine = $totalRows + 2;
                    $count = 0;
                }
            }

            if ($totalRows === 0) {
                throw new RuntimeException('The CSV file contains only the header.');
            }

            if ($count > 0) {
                $jobs[] = new ImportChunkJob(
                        $this->import->id, 
                        $offset, 
                        $count, 
                        $startLine
                    );
            }
           
            $importId = $this->import->id;
            $batch = Bus::batch($jobs)
                ->name("Import #{$importId}")
                ->allowFailures()
                ->then(function (Batch $batch) use ($importId) {
                    Log::info("Import #{$importId} completed");
                })
                ->catch(function (Batch $batch, Throwable $e) use ($importId) {
                    Log::info("Import #{$importId} failed");
                })
                ->finally(function (Batch $batch) use ($importId) {
                    Log::info("Import #{$importId} finished");
                    
                    Import::whereKey($importId)->update([
                        'status' => $batch->failedJobs > 0 ? Import::STATUS_FAILED : Import::STATUS_COMPLETED,
                    ]);
                })
                ->dispatch();

            $this->import->batch_id = $batch->id;
            $this->import->total_rows = $totalRows;
            $this->import->save();
        } catch (Throwable $e) {
            $errosMessage = $e->getMessage();

            Log::info('prepare error', ['error' => $errosMessage]);

            $this->import->status = Import::STATUS_FAILED;
            $this->import->error_message = $errosMessage;
            $this->import->save();

            $this->fail(new RuntimeException($errosMessage));
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        Log::info('prepare end', ['duration' => microtime(true) - $start]);
    }
}
