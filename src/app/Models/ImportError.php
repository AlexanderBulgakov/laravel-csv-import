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
        'raw' => 'array',
        'line' => 'integer',
    ];

    public function import()
    {
        return $this->belongsTo(Import::class);
    }

    public function getErrValueAttribute()
    {
        $errResults = [];

        foreach ($this->errors as $values) {
            $errResults[] = implode('; ', $values);
        }

        return implode(' | ', $errResults);
    }

    public function getRawValueAttribute()
    {
        return implode(' | ', $this->raw);
    }
}
