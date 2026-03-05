<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'LOGIK GAME API',
    description: 'API backend du jeu télévisé LOGIK GAME — gestion des sessions, manches, questions, joueurs, temps réel et projection.',
    contact: new OA\Contact(name: 'NerdX', email: 'contact@nerdx.com'),
)]
#[OA\Server(url: '/api', description: 'API Server')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum Token',
    description: 'Token Sanctum admin (obtenu via POST /admin/login)',
)]
#[OA\SecurityScheme(
    securityScheme: 'playerToken',
    type: 'apiKey',
    in: 'header',
    name: 'X-Player-Token',
    description: 'Token d\'accès joueur (64 caractères, reçu après sélection)',
)]
#[OA\Tag(name: 'Admin Auth', description: 'Authentification administrateur')]
#[OA\Tag(name: 'Sessions', description: 'CRUD des sessions de jeu')]
#[OA\Tag(name: 'Rounds', description: 'Gestion des manches')]
#[OA\Tag(name: 'Questions', description: 'CRUD des questions par manche')]
#[OA\Tag(name: 'Preselection Questions', description: 'Questions de pré-sélection')]
#[OA\Tag(name: 'Game Engine', description: 'Moteur de jeu admin (cycle de vie complet)')]
#[OA\Tag(name: 'Dashboard', description: 'Tableau de bord temps réel')]
#[OA\Tag(name: 'Player Registration', description: 'Inscription des joueurs')]
#[OA\Tag(name: 'Player Game', description: 'Actions joueur en jeu')]
#[OA\Tag(name: 'Player Preselection', description: 'Pré-sélection joueur')]
#[OA\Tag(name: 'Projection', description: 'Écran de projection public')]
abstract class Controller
{
    //
}
