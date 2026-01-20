<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Http\Request;

class AreaController extends Controller
{

    private function formatArea(Area $area): array
    {
        return [
            'id' => $area->id,
            'user_id' => $area->user_id,
            'name' => $area->name,
            'is_active' => (bool) $area->is_active,
            'action_service_name' => $area->action?->service?->name ?? 'Unknown',
            'reaction_service_name' => $area->reaction?->service?->name ?? 'Unknown',
            'action_name' => $area->action?->name ?? 'Unknown',
            'reaction_name' => $area->reaction?->name ?? 'Unknown',
            'action_params' => $area->action_params,
            'reaction_params' => $area->reaction_params,
            'last_executed_at' => $area->last_executed_at?->toISOString(),
        ];
    }


    public function index(Request $request)
    {
        $user = $request->user();
        
        $areas = Area::where('user_id', $user->id)
            ->with(['action.service', 'reaction.service'])
            ->get()
            ->map(function ($area) {
                return $this->formatArea($area);
            });

        return response()->json($areas);
    }


    public function show(Request $request, Area $area)
    {
        // Vérifier que l'AREA appartient bien à l'utilisateur
        if ($area->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $area->load(['action.service', 'reaction.service']);
        
        return response()->json($this->formatArea($area));
    }

    
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'action_id' => 'required|exists:actions,id',
            'reaction_id' => 'required|exists:reactions,id',
            'action_params' => 'nullable|array',
            'reaction_params' => 'nullable|array',
        ]);

        // Créer l'AREA avec les relations directes
        $area = Area::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'action_id' => $validated['action_id'],
            'reaction_id' => $validated['reaction_id'],
            'action_params' => $validated['action_params'] ?? null,
            'reaction_params' => $validated['reaction_params'] ?? null,
            'is_active' => true,
        ]);

        // Recharger avec les relations
        $area->load(['action.service', 'reaction.service']);

        return response()->json($this->formatArea($area), 201);
    }


    public function update(Request $request, Area $area)
    {
        if ($area->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'action_id' => 'sometimes|exists:actions,id',
            'reaction_id' => 'sometimes|exists:reactions,id',
            'action_params' => 'nullable|array',
            'reaction_params' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $area->update($validated);
        $area->load(['action.service', 'reaction.service']);

        return response()->json($this->formatArea($area));
    }


    public function toggle(Request $request, Area $area)
    {
        if ($area->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $area->update(['is_active' => $validated['is_active']]);
        $area->load(['action.service', 'reaction.service']);

        return response()->json($this->formatArea($area));
    }

  
    public function destroy(Request $request, Area $area)
    {
        if ($area->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        $area->delete();

        return response()->json(['message' => 'AREA supprimée avec succès']);
    }
}
