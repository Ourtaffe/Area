<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Action;
use Illuminate\Http\Request;

class ActionController extends Controller
{
   public function index()
{
    $actions = Action::with('service')->get()->map(function ($action) {
        return [
            'id' => $action->id,
            'service_id' => $action->service_id,
            'service_name' => $action->service->name,
            'name' => $action->name,
            'identifier' => $action->identifier,
            'description' => $action->description,
            'parameters_schema' => $action->parameters_schema,
        ];
    });
    
    return response()->json($actions);
}
}
