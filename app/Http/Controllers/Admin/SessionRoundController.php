<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SessionRoundResource;
use App\Models\Session;
use App\Models\SessionRound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SessionRoundController extends Controller
{
    public function index(Session $session): AnonymousResourceCollection
    {
        $rounds = $session->rounds()
            ->withCount('questions')
            ->get();

        return SessionRoundResource::collection($rounds);
    }

    public function update(Request $request, Session $session, SessionRound $round): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'string', 'max:255'],
            'rules_description' => ['nullable', 'string', 'max:2000'],
        ]);

        // Faire en sorte qu'au moins 1 des manches 1-3 reste active
        if (isset($validated['is_active']) && ! $validated['is_active'] && $round->round_number <= 3) {
            $activeFirstRoundsCount = $session->rounds()
                ->where('round_number', '<=', 3)
                ->where('is_active', true)
                ->where('id', '!=', $round->id)
                ->count();

            if ($activeFirstRoundsCount === 0) {
                return response()->json([
                    'message' => 'Au moins une des manches 1 à 3 doit rester active.',
                ], 422);
            }
        }

        // Les manches 5-8 sont obligatoires
        if (isset($validated['is_active']) && ! $validated['is_active'] && $round->round_number >= 5) {
            return response()->json([
                'message' => 'Les manches 5 à 8 sont obligatoires et ne peuvent pas être désactivées.',
            ], 422);
        }

        $round->update($validated);

        return response()->json(new SessionRoundResource($round));
    }
}
