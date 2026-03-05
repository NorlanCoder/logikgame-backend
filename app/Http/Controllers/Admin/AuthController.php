<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(
        path: '/admin/login',
        summary: 'Connexion admin',
        description: 'Authentifie un administrateur et retourne un token Sanctum.',
        tags: ['Admin Auth'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@logikgame.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Connexion réussie', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'admin', type: 'object', properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'email', type: 'string'),
                        new OA\Property(property: 'avatar', type: 'string', nullable: true),
                    ]),
                ],
            )),
            new OA\Response(response: 401, description: 'Identifiants invalides'),
            new OA\Response(response: 422, description: 'Validation échouée'),
        ],
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        $admin = Admin::query()
            ->where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (! $admin || ! Hash::check($request->password, $admin->password)) {
            return response()->json([
                'message' => 'Identifiants invalides.',
            ], 401);
        }

        $admin->update(['last_login_at' => now()]);

        $token = $admin->createToken('admin-token', ['admin'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'avatar' => $admin->avatar,
            ],
        ]);
    }

    #[OA\Post(
        path: '/admin/logout',
        summary: 'Déconnexion admin',
        description: 'Révoque le token Sanctum courant.',
        security: [['sanctum' => []]],
        tags: ['Admin Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Déconnecté', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'message', type: 'string')],
            )),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté avec succès.']);
    }

    #[OA\Get(
        path: '/admin/me',
        summary: 'Profil admin',
        description: 'Retourne les informations de l\'administrateur connecté.',
        security: [['sanctum' => []]],
        tags: ['Admin Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Profil admin', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'email', type: 'string'),
                    new OA\Property(property: 'avatar', type: 'string', nullable: true),
                    new OA\Property(property: 'last_login_at', type: 'string', format: 'date-time', nullable: true),
                ],
            )),
            new OA\Response(response: 401, description: 'Non authentifié'),
        ],
    )]
    public function me(Request $request): JsonResponse
    {
        $admin = $request->user();

        return response()->json([
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'avatar' => $admin->avatar,
            'last_login_at' => $admin->last_login_at?->toIso8601String(),
        ]);
    }
}
