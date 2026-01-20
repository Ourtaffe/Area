<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileBuild extends Model
{
    /** @use HasFactory<\Database\Factories\MobileBuildFactory> */
    use HasFactory;

    protected $table = 'mobile_builds';

    protected $fillable = [
    'version',
    'apk_path',    
    ];

    public $timestamps = false;
}
