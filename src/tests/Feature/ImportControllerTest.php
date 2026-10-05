<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use App\Models\Import;
use App\Jobs\PrepareImportJob;

class ImportControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_upload_creates_import_and_dispatches_job()
    {
        Storage::fake('local');
        Bus::fake();

        $file = UploadedFile::fake()
            ->createWithContent(
                'test.csv',
                "external_id,created_at,first_name,last_name,phone,email,city,source,utm_campaign,product,budget_uah,status,manager,comment,next_contact_at
LD-000001,2025-07-10 05:06:54,First,Last,+381234567890,test@test.test,city,Facebook Ads,catalog_2026,Контекстна реклама,23 700,in_progress,Manager,comment,2025-07-23 05:06:54
"
            );

        $response = $this->post(route('imports.store'), [
            'csv_file' => $file,
        ]);

        $import = Import::latest('id')->first();
        $this->assertNotNull($import);

        $response->assertRedirect(route('imports.show', $import));

        $this->assertDatabaseHas('imports', [
            'id' => $import->id,
            'status' => 'pending',
        ]);

        Storage::disk('local')->assertExists($import->path);

        Bus::assertDispatched(
            PrepareImportJob::class,
            function ($job) use ($import) {
                return $job->import->is($import);
            }
        );
    }

    public function test_jpg_file_fails_validation_and_job_is_not_dispatched()
    {
        Storage::fake();
        Bus::fake();

        $file = UploadedFile::fake()->create(
            'test.jpg',
            100,
            'image/jpeg'
        );

        $response = $this->post(route('imports.store'), [
            'csv_file' => $file,
        ]);

        $this->assertDatabaseCount('imports', 0);

        $response->assertSessionHasErrors('csv_file');

        Bus::assertNotDispatched(PrepareImportJob::class);
    }

    public function test_status_returns_correct_json_structure()
    {
        $import = Import::factory()->create();

        $response = $this->get(route('imports.status', $import));

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'progress',
            'finished',
            'status',
            'total_rows',
            'processed_rows',
            'failed_rows',
            'error_message',
        ]);

        $response->assertJson([
            'progress' => 0,
            'finished' => false,
            'status' => 'pending',
            'total_rows' => 0,
            'processed_rows' => 0,
            'failed_rows' => 0,
            'error_message' => null,
        ]);
    }

    public function test_processing_import_returns_progress_percent()
    {
        $import = Import::factory()->processing(200, 100)->create();

        $response = $this->get(route('imports.status', $import));

        $response->assertStatus(200);

        $response->assertJson([
            'progress' => 50,
            'finished' => false,
            'status' => 'processing',
            'total_rows' => 200,
            'processed_rows' => 100,
            'failed_rows' => 0,
            'error_message' => null,
        ]);
    }

    public function test_failed_import_without_batch_is_finished()
    {
        $import = Import::factory()->failed('Test', 200)->create();

        $response = $this->get(route('imports.status', $import));

        $response->assertStatus(200);

        $response->assertJson([
            'progress' => 0,
            'finished' => true,
            'status' => 'failed',
            'total_rows' => 200,
            'processed_rows' => 0,
            'failed_rows' => 1,
            'error_message' => 'Test',
        ]);
    }

    public function test_empty_form_fails_validation()
    {
        Storage::fake();
        Bus::fake();

        $response = $this->post(route('imports.store'), [
            'csv_file' => [],
        ]);

        Bus::assertNotDispatched(PrepareImportJob::class);
        $response->assertSessionHasErrors('csv_file');
    }
}
