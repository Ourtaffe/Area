<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TriggerHistory extends Model
{
    /** @use HasFactory<\Database\Factories\TriggerHistoryFactory> */
    use HasFactory;

    protected $table = 'trigger_history';

    protected $fillable = [
        'area_id',
        'action_snapshot',
        'executed_at',
    ];

    protected $casts = [
        'action_snapshot' => 'array',
        'executed_at' => 'datetime',
    ];

    public $timestamps = false;

    public function area()
    {
        return $this -> belongsTo(Area::class);
    }
}
