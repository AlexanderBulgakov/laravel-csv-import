<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\Import;
use App\Imports\CsvLeadHeader;
use App\Imports\LeadRowProcessor;
use RuntimeException;

class ImportChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, Batchable;

    public int $importId;
    public int $offset;
    public int $limit;
    public int $startLine;

    public int $tries = 3;
    public int $timeout = 30;
    public int $backoff = 60;

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
        if ($this->batch()->cancelled()) {
            return;
        }

        $import = Import::find($this->importId);

        if ($import === null) {
            $this->fail(new RuntimeException('Import not found.'));
            return;
        }

        $leadRowProcessor = new LeadRowProcessor(CsvLeadHeader::getHeader());

        $handle = fopen(Storage::path($import->path), 'r');

        fseek($handle, $this->offset);

        $i = 0;
        $rowsData = [];
        $rowsErrors = [];
        $now = now();

        while (($row = fgetcsv($handle)) !== false) {
            $result = $leadRowProcessor->process($row);

            if (!is_null($result['data'])) {
                $data = $result['data'];
                $key = $data['external_id'];

                $data['created_at'] = $now;
                $data['updated_at'] = $now;

                $rowsData[$key] = $data;
            }

            if (!empty($result['errors'])) {
                $rowsErrors[] = [
                    'import_id' => $this->importId,
                    'line' => $this->startLine + $i,
                    'errors' => json_encode($result['errors']),
                    'raw' => json_encode($row, JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $i++;

            if ($i === $this->limit) {
                break;
            }
        }

        if (is_resource($handle)) {
            fclose($handle);
        }

        $this->save($rowsData, $rowsErrors, $i);
    }

    private function save(array $rowsData, array $rowsErrors, int $processed): void
    {
        DB::transaction(function () use ($rowsData, $rowsErrors, $processed) {
            if (!empty($rowsData)) {
                $columns = array_map(
                    fn($value) => $value === 'created_at' ? 'lead_created_at' : $value,
                    CsvLeadHeader::getHeader()
                );
                $columns[] = 'updated_at';

                $key = array_search('external_id', $columns, true);
                if ($key !== false) {
                    unset($columns[$key]);
                }

                DB::table('leads')->upsert(
                    array_values($rowsData),
                    ['external_id'],
                    $columns
                );
            }

            if (!empty($rowsErrors)) {
                DB::table('import_errors')->insert($rowsErrors);
            }

            Import::whereKey($this->importId)
                ->increment(
                    'processed_rows',
                    $processed,
                    [
                        'failed_rows' => DB::raw(
                            'failed_rows + ' . (int) count($rowsErrors)
                        ),
                    ]
                );
        });
    }
}
