<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hook extends Model
{
    /** @use HasFactory<\Database\Factories\HookFactory> */
    use HasFactory;

    protected $fillable = [
    'area_id',
    'status',
    'execution_log',
    ];

    protected $casts = [
        'execution_log' => 'array',
    ];

    public $timestamps = false;

    public function area()
    {
        return $this -> belongsTo(Area::class);
    }
}
