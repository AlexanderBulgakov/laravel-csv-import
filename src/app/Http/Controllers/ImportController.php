<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreImportRequest;
use App\Models\Import;
use App\Jobs\PrepareImportJob;

class ImportController extends Controller
{
    public function create(){
        return view('imports.create');
    }

    public function store(StoreImportRequest $request){
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
    
    public function show(Import $import){
        return view('imports.show', compact('import'));
    }
}
