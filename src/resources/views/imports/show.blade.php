@extends('layouts.app')

@section('title', 'Import')

@section('content')

    <h1>CSV file - {{ $import->original_name }}</h1>

    @if (session('success'))
        <ul class="success">
            <li>{{ session('success') }}</li>
        </ul>
    @endif

    <ul>
        <li>Status: {{ $import->status }}</li>
        <li>Total rows: {{ $import->total_rows }}</li>
        <li>Processed rows: {{ $import->processed_rows }}</li>
        <li>Failed rows: {{ $import->failed_rows }}</li>
    </ul>
@endsection
