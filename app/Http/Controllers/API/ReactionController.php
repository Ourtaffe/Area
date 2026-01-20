<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Reaction;
use Illuminate\Http\Request;

class ReactionController extends Controller
{
    public function index()
{
    $reactions = Reaction::with('service')->get()->map(function ($reaction) {
        return [
            'id' => $reaction->id,
            'service_id' => $reaction->service_id,
            'service_name' => $reaction->service->name,
            'name' => $reaction->name,
            'identifier' => $reaction->identifier,
            'description' => $reaction->description,
            'config_schema' => $reaction->config_schema,
        ];
    });
    
    return response()->json($reactions);
}
}
