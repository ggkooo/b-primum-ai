<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dataset extends Model
{
    protected $fillable = [
        'original_filename',
        'storage_path',
        'parsed_path',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
