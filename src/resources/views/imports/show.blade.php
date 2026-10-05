@extends('layouts.app')

@section('title', 'Import result')

@section('content')
    <p><a href="{{ route('imports.create') }}">New import</a></p>
    <h1>CSV file - {{ $import->original_name }}</h1>

    @if (session('success'))
        <ul class="success">
            <li>{{ session('success') }}</li>
        </ul>
    @endif
    
    <div class="js-progress" data-finished="{{ $finished ? 1 : 0 }}" data-url="{{ route('imports.status', $import->id) }}">
        <div class="progress">
            <div class="progress-bar" style="width: {{ $progress }}%;">
                <span class="progress-value">{{ $progress }}%</span>
            </div>
        </div>
        <ul>
            <li>Status: <span class="js-status">{{ $import->status }}</span></li>
            <li>Total rows: <span class="js-total_rows">{{ $import->total_rows }}</span></li>
            <li>Processed rows: <span class="js-processed_rows">{{ $import->processed_rows }}</span></li>
            <li>Failed rows: <span class="js-failed_rows">{{ $import->failed_rows }}</span></li>
            <li>Error message: <span class="js-error_message">{{ $import->error_message }}</span></li>
        </ul>
        <p><a href="{{ route('imports.errors', $import->id) }}">Click here to view the rows that failed to process.</a></p>
    </div>
@endsection
