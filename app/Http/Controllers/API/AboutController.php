<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;

class AboutController extends Controller
{
    public function index(Request $request)
    {
        $clientHost = $request->ip();
        $currentTime = now()->timestamp;

        $services = Service::with(['actions', 'reactions'])->get()->map(function ($service) {
            return [
                'name' => $service->name,
                'actions' => $service->actions->map(function ($action) {
                    return [
                        'name' => $action->name,
                        'description' => $action->description,
                    ];
                }),
                'reactions' => $service->reactions->map(function ($reaction) {
                    return [
                        'name' => $reaction->name,
                        'description' => $reaction->description,
                    ];
                }),
            ];
        });

        return response()->json([
            'client' => [
                'host' => $clientHost,
            ],
            'server' => [
                'current_time' => $currentTime,
                'services' => $services,
            ],
        ]);
    }
}
