<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoundStatus;
use App\Enums\RoundType;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSessionRequest;
use App\Http\Requests\Admin\UpdateSessionRequest;
use App\Http\Resources\SessionResource;
use App\Models\Session;
use App\Models\SessionRound;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SessionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $sessions = Session::query()
            ->where('admin_id', $request->user()->id)
            ->withCount(['registrations', 'rounds'])
            ->orderByDesc('scheduled_at')
            ->get();

        return SessionResource::collection($sessions);
    }

    public function store(StoreSessionRequest $request): JsonResponse
    {
        $session = Session::create([
            ...$request->validated(),
            'admin_id' => $request->user()->id,
            'status' => SessionStatus::Draft,
            'projection_code' => strtoupper(substr(md5(uniqid()), 0, 8)),
        ]);

        $this->createDefaultRounds($session);

        return response()->json(
            new SessionResource($session->load('rounds')),
            201
        );
    }

    public function show(Session $session): SessionResource
    {
        $session->load([
            'rounds' => fn ($q) => $q->withCount('questions'),
            'currentRound',
        ])->loadCount(['registrations', 'sessionPlayers']);

        return new SessionResource($session);
    }

    public function update(UpdateSessionRequest $request, Session $session): JsonResponse
    {
        if (! in_array($session->status, [SessionStatus::Draft, SessionStatus::RegistrationOpen])) {
            return response()->json([
                'message' => 'La session ne peut être modifiée que si elle est en brouillon ou en inscription ouverte.',
            ], 422);
        }

        $session->update($request->validated());

        return response()->json(new SessionResource($session));
    }

    public function destroy(Session $session): JsonResponse
    {
        if ($session->status !== SessionStatus::Draft) {
            return response()->json([
                'message' => 'Seules les sessions en brouillon peuvent être supprimées.',
            ], 422);
        }

        $session->delete();

        return response()->json(null, 204);
    }

    /**
     * Crée les 8 manches par défaut pour une nouvelle session.
     *
     * @param  array<int, array{number: int, type: RoundType, name: string, active: bool}>  $roundDefinitions
     */
    private function createDefaultRounds(Session $session): void
    {
        $roundDefinitions = [
            ['number' => 1, 'type' => RoundType::SuddenDeath, 'name' => 'Mort subite', 'active' => true],
            ['number' => 2, 'type' => RoundType::Hint, 'name' => 'Utilisation d\'indice', 'active' => false],
            ['number' => 3, 'type' => RoundType::SecondChance, 'name' => 'Seconde chance', 'active' => false],
            ['number' => 4, 'type' => RoundType::RoundSkip, 'name' => 'Passage de manche', 'active' => false],
            ['number' => 5, 'type' => RoundType::Top4Elimination, 'name' => 'Élimination top 4', 'active' => true],
            ['number' => 6, 'type' => RoundType::DuelJackpot, 'name' => 'Duel — Tour de rôle', 'active' => true],
            ['number' => 7, 'type' => RoundType::DuelElimination, 'name' => 'Duel — Élimination', 'active' => true],
            ['number' => 8, 'type' => RoundType::Finale, 'name' => 'Finale', 'active' => true],
        ];

        foreach ($roundDefinitions as $index => $definition) {
            SessionRound::create([
                'session_id' => $session->id,
                'round_number' => $definition['number'],
                'round_type' => $definition['type'],
                'name' => $definition['name'],
                'is_active' => $definition['active'],
                'status' => RoundStatus::Pending,
                'display_order' => $index + 1,
            ]);
        }
    }
}
