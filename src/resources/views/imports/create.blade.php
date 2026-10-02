@extends('layouts.app')

@section('title', 'Import')

@section('content')

    <h1>CSV Upload</h1>

    @if ($errors->any())
        <ul class="errors">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form action="{{ route('imports.store') }}"
          method="POST"
          enctype="multipart/form-data">

        @csrf

        <div class="input-file-holder">
            <label>
                <span>CSV file</span>
                <input type="file" name="csv_file">
            </label>
        </div>

        <button type="submit" class="submit-button">Upload</button>
    </form>

@endsection
