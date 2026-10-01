<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'lead_created_at',//created_at csv
        'first_name',
        'last_name',
        'phone',
        'email',
        'city',
        'source',
        'utm_campaign',
        'product',
        'budget_uah',
        'status',
        'manager',
        'comment',
        'next_contact_at',
    ];

    protected $casts = [
        'lead_created_at' => 'datetime',
        'next_contact_at' => 'datetime',
        'budget_uah' => 'integer',
    ];
}
