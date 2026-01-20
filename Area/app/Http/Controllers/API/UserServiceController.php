<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\UserService;
use Illuminate\Http\Request;

class UserServiceController extends Controller
{
    /**
     * Display a listing of user's connected services.
     */
    public function index(Request $request)
    {
        $userServices = UserService::where('user_id', $request->user()->id)
            ->with('service')
            ->get()
            ->map(function ($userService) {
                return [
                    'id' => $userService->id,
                    'user_id' => $userService->user_id,
                    'service_id' => $userService->service_id,
                    'service_name' => $userService->service->name ?? 'Unknown',
                    'config' => $userService->config,
                    'created_at' => $userService->created_at,
                    'updated_at' => $userService->updated_at,
                    'service' => $userService->service,
                ];
            });

        return response()->json([
            'response_code' => 200,
            'status' => 'success',
            'data' => $userServices
        ]);
    }

    /**
     * Store a newly created user service connection.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'config' => 'nullable|array',
        ]);

        $userService = UserService::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'service_id' => $validated['service_id']
            ],
            [
                'config' => $validated['config'] ?? [],
            ]
        );

        return response()->json([
            'response_code' => 201,
            'status' => 'success',
            'message' => 'Service connected successfully',
            'data' => $userService->load('service')
        ], 201);
    }

    /**
     * Display the specified user service.
     */
    public function show(Request $request, $id)
    {
        $userService = UserService::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->with('service')
            ->firstOrFail();

        return response()->json([
            'response_code' => 200,
            'status' => 'success',
            'data' => $userService
        ]);
    }

    /**
     * Update the specified user service.
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'config' => 'nullable|array',
        ]);

        $userService = UserService::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $userService->update([
            'config' => $validated['config'] ?? $userService->config,
        ]);

        return response()->json([
            'response_code' => 200,
            'status' => 'success',
            'message' => 'Service updated successfully',
            'data' => $userService->load('service')
        ]);
    }

    /**
     * Remove the specified user service connection.
     */
    public function destroy(Request $request, $id)
    {
        $userService = UserService::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $userService->delete();

        return response()->json([
            'response_code' => 200,
            'status' => 'success',
            'message' => 'Service disconnected successfully'
        ]);
    }
}
