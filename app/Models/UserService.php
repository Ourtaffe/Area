<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserService extends Model
{
    /** @use HasFactory<\Database\Factories\UserServiceFactory> */
    use HasFactory;

    protected $table = 'user_services';

    protected $fillable = [
        'user_id',
        'service_id',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}