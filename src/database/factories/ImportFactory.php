<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Import;

class ImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'original_name' => 'test.csv',
            'path' => 'imports/test.csv',
            'status' => Import::STATUS_PENDING,
            'total_rows' => 0,
            'processed_rows' => 0,
            'failed_rows' => 0,
            'batch_id' => null,
            'error_message' => null,
        ];
    }

    public function processing(int $total, int $processed)
    {
        return $this->state([
            'status' => Import::STATUS_PROCESSING,
            'total_rows' => $total,
            'processed_rows' => $processed,
        ]);
    }

    public function failed(string $errorMessage, int $totalRows)
    {
        return $this->state([
            'status' => Import::STATUS_FAILED,
            'failed_rows' => 1,
            'error_message' => $errorMessage,
            'total_rows' => $totalRows,
        ]);
    }
}
