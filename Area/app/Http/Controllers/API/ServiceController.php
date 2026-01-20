<?php
namespace App\Http\Controllers\API;
use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::with(['actions', 'reactions'])->get()->map(function ($service) {
            return [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'auth_type' => $service->auth_type,
                'actions' => $service->actions->map(function ($action) {
                    return [
                        'id' => $action->id,
                        'name' => $action->name,
                        'identifier' => $action->identifier,
                        'description' => $action->description,
                        'parameters_schema' => $action->parameters_schema,
                    ];
                }),
                'reactions' => $service->reactions->map(function ($reaction) {
                    return [
                        'id' => $reaction->id,
                        'name' => $reaction->name,
                        'identifier' => $reaction->identifier,
                        'description' => $reaction->description,
                        'parameters_schema' => $reaction->parameters_schema,
                    ];
                }),
            ];
        });
        
        return response()->json($services);
    }
}