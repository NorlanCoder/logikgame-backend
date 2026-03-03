<?php

namespace App\Http\Controllers\Player;

use App\Enums\RegistrationStatus;
use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Player\StoreRegistrationRequest;
use App\Http\Resources\RegistrationResource;
use App\Models\Player;
use App\Models\Registration;
use App\Models\Session;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RegistrationController extends Controller
{
    /**
     * Inscrit un joueur à une session.
     * Crée le joueur si inconnu, ou réutilise le profil existant.
     */
    public function store(StoreRegistrationRequest $request): JsonResponse
    {
        $session = Session::findOrFail($request->session_id);

        if ($session->status !== SessionStatus::RegistrationOpen) {
            return response()->json([
                'message' => 'Les inscriptions ne sont pas ouvertes pour cette session.',
            ], 422);
        }

        $result = DB::transaction(function () use ($request, $session) {
            // Trouver ou créer le joueur par email
            $player = Player::firstOrCreate(
                ['email' => $request->email],
                [
                    'full_name' => $request->full_name,
                    'phone' => $request->phone,
                    'pseudo' => $request->pseudo,
                ]
            );

            // Vérifier si déjà inscrità cette session
            $existingRegistration = Registration::query()
                ->where('session_id', $session->id)
                ->where('player_id', $player->id)
                ->first();

            if ($existingRegistration) {
                return ['existing' => true, 'registration' => $existingRegistration];
            }

            // Vérifier l'unicité du pseudo pour cette session (via les joueurs déjà inscrits)
            $pseudoTaken = Player::query()
                ->whereHas('registrations', fn ($q) => $q->where('session_id', $session->id))
                ->where('pseudo', $request->pseudo)
                ->where('id', '!=', $player->id)
                ->exists();

            if ($pseudoTaken) {
                abort(422, 'Ce pseudo est déjà utilisé dans cette session.');
            }

            $registration = Registration::create([
                'session_id' => $session->id,
                'player_id' => $player->id,
                'status' => RegistrationStatus::Registered,
                'registered_at' => now(),
            ]);

            return ['existing' => false, 'registration' => $registration];
        });

        if ($result['existing']) {
            $result['registration']->load('player');

            return response()->json([
                'message' => 'Vous êtes déjà inscrit à cette session.',
                'registration' => new RegistrationResource($result['registration']),
            ], 200);
        }

        $result['registration']->load('player');

        return response()->json(
            new RegistrationResource($result['registration']),
            201
        );
    }

    /**
     * Récupère le statut d'une inscription par son ID.
     */
    public function show(Registration $registration): RegistrationResource
    {
        $registration->load(['session:id,name,status,scheduled_at', 'player:id,full_name,pseudo']);

        return new RegistrationResource($registration);
    }
}
