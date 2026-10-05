@extends('layouts.app')

@section('title', 'Import errors')

@section('content')
    <p><a href="{{ route('imports.show', $import->id) }}">Back</a></p>
    <h1>CSV file - {{ $import->original_name }}</h1>

    @if ($importErrors->isNotEmpty())
        <p><strong><em>Please correct the errors and try the import again.</em></strong></p>
        <div class="import-errors-holder">
            @foreach($importErrors as $importError)
                <ul class="import-error">
                    <li><strong>Line:</strong> {{ $importError->line }}</li>
                    <li><strong>Errors:</strong> {{ $importError->err_value }}</li>
                    <li><strong>Raw data:</strong> {{ $importError->raw_value }}</li>
                </ul>
            @endforeach
            {{ $importErrors->links('vendor.pagination.default') }}
        </div>
    @else
        <p>No errors were detected during import.</p>
    @endif
@endsection
