<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportError extends Model
{
    use HasFactory;

    protected $fillable = [
        'import_id',
        'line',
        'errors',
        'raw',
    ];

    protected $casts = [
        'errors' => 'array',
        'line' => 'integer',
    ];

    public function import()
    {
        return $this->belongsTo(Import::class);
    }
}
