<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'original_name',
        'path',
        'status',
        'total_rows',
        'batch_id',
        'error_message'
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'failed_rows' => 'integer',
    ];

    public function errors()
    {
        return $this->hasMany(ImportError::class);
    }

    public function isFinished(): bool
    {
        return $this->status === Import::STATUS_COMPLETED || $this->status === Import::STATUS_FAILED;
    }
}
