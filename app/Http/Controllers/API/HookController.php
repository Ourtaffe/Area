<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Hook;
use Illuminate\Http\Request;

class HookController extends Controller
{
    public function index(Request $request)
    {
        $hooks = Hook::where('user_id', $request->user()->id)->with(['area', 'area.action', 'area.reaction'])->get();
        return response()->json($hooks);
    }

    public function store(Request $request)
    {
        $request->validate([
            'area_id' => 'required|exists:areas,id',
            'status' => 'required|string',
        ]);

        $hook = Hook::create([
            'user_id' => $request->user()->id,
            'area_id' => $request->area_id,
            'status' => $request->status,
        ]);

        return response()->json($hook, 201);
    }

    public function show(Hook $hook)
    {
        if ($hook->user_id !== request()->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $hook->load(['area', 'area.action', 'area.reaction']);
        return response()->json($hook);
    }

    public function update(Request $request, Hook $hook)
    {
        if ($hook->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'status' => 'string',
        ]);

        $hook->update($request->only(['status']));
        return response()->json($hook);
    }

    public function destroy(Hook $hook)
    {
        if ($hook->user_id !== request()->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        $hook->delete();
        return response()->json(['message' => 'Hook deleted']);
    }
}
