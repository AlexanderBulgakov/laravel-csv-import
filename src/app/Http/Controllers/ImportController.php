<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Models\Import;
use App\Jobs\PrepareImportJob;

class ImportController extends Controller
{
    public function create()
    {
        return view('imports.create');
    }

    public function store(StoreImportRequest $request)
    {
        $file = $request->file('csv_file');
        $path = $file->store('imports');

        $import = Import::create([
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
        ]);

        PrepareImportJob::dispatch($import);

        return redirect()
            ->route('imports.show', $import)
            ->with('success', 'File successfully uploaded.');
    }

    public function show(Import $import)
    {
        $statusPayload = $this->statusPayload($import);
        $progress = $statusPayload['progress'];
        $finished = $statusPayload['finished'];

        return view('imports.show', [
            'import' => $import,
            'progress' => $progress,
            'finished' => $finished,
        ]);
    }

    public function status(Import $import)
    {
        return response()->json($this->statusPayload($import));
    }

    public function errors(Import $import)
    {
        $importErrors = $import->errors()->orderBy('line')->paginate(50);

        return view('imports.errors', [
            'import' => $import,
            'importErrors' => $importErrors,
        ]);
    }

    private function statusPayload(Import $import): array
    {
        return [
            'progress' => $import->total_rows > 0
                ? (int) floor($import->processed_rows / $import->total_rows * 100)
                : 0,
            'finished' => $import->isFinished(),
            'status' => $import->status,
            'total_rows' => (int) $import->total_rows,
            'processed_rows' => (int) $import->processed_rows,
            'failed_rows' => (int) $import->failed_rows,
            'error_message' => $import->error_message,
        ];
    }
}
