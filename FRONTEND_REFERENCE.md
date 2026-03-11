# LOGIK GAME — Documentation Frontend de Référence

> Document complet pour le développement de l'interface frontend Next.js.
> Basé sur le backend Laravel (API REST + WebSocket Reverb).

---

## Table des matières

1. [Présentation du projet](#1-présentation-du-projet)
2. [Les 3 interfaces](#2-les-3-interfaces)
3. [Architecture technique recommandée](#3-architecture-technique-recommandée)
4. [Authentification et autorisation](#4-authentification-et-autorisation)
5. [API REST — Routes complètes](#5-api-rest--routes-complètes)
6. [Formats de réponse API (Resources)](#6-formats-de-réponse-api-resources)
7. [WebSocket — Événements temps réel](#7-websocket--événements-temps-réel)
8. [Enums et statuts](#8-enums-et-statuts)
9. [Flow complet du jeu](#9-flow-complet-du-jeu)
10. [Détail des 8 manches](#10-détail-des-8-manches)
11. [Système de cagnotte](#11-système-de-cagnotte)
12. [Spécifications UI par interface](#12-spécifications-ui-par-interface)
13. [Gestion des erreurs et edge cases](#13-gestion-des-erreurs-et-edge-cases)

---

## 1. Présentation du projet

**LOGIK GAME** est un jeu interactif en temps réel inspiré de l'émission « 100% Logique ». Un administrateur pilote des sessions de jeu où des joueurs s'affrontent sur **8 manches éliminatoires** pour remporter une **cagnotte cumulative**.

### Cycle de vie d'une session

```
Draft → RegistrationOpen → RegistrationClosed → Preselection → Ready → InProgress → Ended
                                                                              ↓
                                                                          Cancelled / Paused
```

### Principes fondamentaux

- **L'administrateur contrôle tout** : chaque question est lancée manuellement, chaque transition est décidée par l'admin.
- **Aucune automatisation** côté client : le frontend réagit aux événements WebSocket émis par le serveur.
- **Capital initial** : chaque joueur commence avec 1 000 points. À chaque élimination, 1 000 rejoignent la cagnotte.
- **Réponse unique** : un joueur ne peut soumettre qu'une seule réponse par question, non modifiable.
- **Timer serveur** : le timer est géré côté serveur pour l'équité. Le client affiche un décompte local synchronisé.

---

## 2. Les 3 interfaces

| Interface | Rôle | Accès | Responsive |
|-----------|------|-------|------------|
| **Admin** | Pilotage complet du jeu | Login email/password → token Sanctum | Desktop préféré |
| **Joueur** | Participation interactive (répondre, indices, etc.) | Lien unique avec `access_token` | Mobile obligatoire |
| **Projection** | Affichage lecture seule (écran public) | URL + code d'accès 6 caractères | Grand écran 1920×1080+ |

### Ce que chaque interface voit

| Élément | Admin | Joueur | Projection |
|---------|-------|--------|------------|
| Question avant lancement | ✅ | ❌ | ❌ |
| Réponse correcte (avant reveal) | ✅ | ❌ | ❌ |
| Réponses individuelles en temps réel | ✅ | Sa propre réponse | ❌ |
| Timer | ✅ | ✅ | ✅ |
| Liste des éliminés | ✅ (dashboard) | Notification personnelle | ✅ (liste publique) |
| Contrôle du rythme | ✅ (exclusif) | ❌ | ❌ |
| Cagnotte | ✅ | ✅ | ✅ |

---

## 3. Architecture technique recommandée

### Stack Frontend

```
Next.js 14+ (App Router)
├── TypeScript
├── Tailwind CSS
├── Laravel Echo + Pusher.js (WebSocket client)
├── Axios ou fetch (API calls)
├── Zustand ou Context API (state management)
└── Framer Motion (animations)
```

### Structure de dossiers suggérée

```
src/
├── app/
│   ├── admin/                    # Interface Admin
│   │   ├── login/
│   │   ├── sessions/
│   │   │   ├── [id]/
│   │   │   │   ├── rounds/
│   │   │   │   ├── questions/
│   │   │   │   ├── preselection/
│   │   │   │   ├── game/        # Pilotage en direct
│   │   │   │   └── dashboard/
│   │   │   └── create/
│   │   └── layout.tsx
│   ├── player/                   # Interface Joueur
│   │   ├── register/
│   │   ├── preselection/
│   │   ├── game/                 # Salle de jeu
│   │   └── layout.tsx
│   ├── projection/               # Interface Projection
│   │   ├── [code]/
│   │   └── auth/
│   └── layout.tsx
├── components/
│   ├── admin/
│   ├── player/
│   ├── projection/
│   └── shared/
│       ├── Timer.tsx
│       ├── QuestionDisplay.tsx
│       ├── JackpotCounter.tsx
│       └── MediaPlayer.tsx
├── hooks/
│   ├── useEcho.ts               # WebSocket connection
│   ├── useTimer.ts
│   ├── useGameState.ts
│   └── useApi.ts
├── lib/
│   ├── api.ts                   # API client configuration
│   ├── echo.ts                  # Laravel Echo setup
│   └── types.ts                 # TypeScript interfaces
├── stores/
│   ├── gameStore.ts
│   ├── sessionStore.ts
│   └── playerStore.ts
└── utils/
```

### Configuration Laravel Echo

```typescript
// lib/echo.ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'reverb',
  key: process.env.NEXT_PUBLIC_REVERB_APP_KEY,
  wsHost: process.env.NEXT_PUBLIC_REVERB_HOST,
  wsPort: process.env.NEXT_PUBLIC_REVERB_PORT,
  wssPort: process.env.NEXT_PUBLIC_REVERB_PORT,
  forceTLS: false,
  enabledTransports: ['ws', 'wss'],
});

export default echo;
```

---

## 4. Authentification et autorisation

### 4.1 Admin — Sanctum Bearer Token

```
POST /api/admin/login
Body: { "email": "...", "password": "..." }
→ { "token": "1|abc123...", "admin": { id, name, email, avatar } }
```

Toutes les requêtes admin utilisent :
```
Authorization: Bearer {token}
```

### 4.2 Joueur — Access Token

Le joueur reçoit un `access_token` après sélection. Cet access token est stocké dans `SessionPlayer.access_token`.

```
Authorization: Bearer {access_token}
```

Middleware backend : `player.token`

### 4.3 Projection — Code d'accès

```
POST /api/projection/authenticate
Body: { "access_code": "ABC123" }  // 6 caractères
```

---

## 5. API REST — Routes complètes

**Base URL** : `http://{HOST}/api`

### 5.1 Santé

| Méthode | Route | Auth | Description |
|---------|-------|------|-------------|
| GET | `/ping` | ❌ | Vérification santé API |

**Réponse :**
```json
{ "status": "ok", "timestamp": "2026-03-05T08:54:27+00:00" }
```

---

### 5.2 Admin — Auth

| Méthode | Route | Auth | Body |
|---------|-------|------|------|
| POST | `/admin/login` | ❌ | `{ "email": string, "password": string (min:6) }` |
| POST | `/admin/logout` | 🔒 Admin | — |
| GET | `/admin/me` | 🔒 Admin | — |

**Login Response :**
```json
{
  "token": "1|pdkiBjZ2QzTeSO5r...",
  "admin": {
    "id": 1,
    "name": "Admin LogikGame",
    "email": "admin@logikgame.com",
    "avatar": null
  }
}
```

---

### 5.3 Admin — Sessions CRUD

| Méthode | Route | Auth | Description |
|---------|-------|------|-------------|
| GET | `/admin/sessions` | 🔒 Admin | Liste des sessions |
| POST | `/admin/sessions` | 🔒 Admin | Créer une session |
| GET | `/admin/sessions/{id}` | 🔒 Admin | Détail session |
| PUT | `/admin/sessions/{id}` | 🔒 Admin | Modifier session |
| DELETE | `/admin/sessions/{id}` | 🔒 Admin | Supprimer (status=draft uniquement) |

**Body POST/PUT :**
```json
{
  "name": "LOGIK S01E01",              // requis
  "scheduled_at": "2026-03-10T20:00:00Z", // requis, date future
  "max_players": 100,                  // requis, 2-1000
  "description": "Description...",     // optionnel
  "cover_image_url": "https://...",    // optionnel
  "registration_opens_at": "...",      // optionnel
  "registration_closes_at": "...",     // optionnel
  "preselection_opens_at": "...",      // optionnel
  "preselection_closes_at": "...",     // optionnel
  "reconnection_delay": 10            // optionnel, 5-120 secondes
}
```

---

### 5.4 Admin — Rounds (Manches)

| Méthode | Route | Auth | Description |
|---------|-------|------|-------------|
| GET | `/admin/sessions/{session}/rounds` | 🔒 Admin | Liste des 8 manches |
| PATCH | `/admin/sessions/{session}/rounds/{round}` | 🔒 Admin | Modifier une manche |

**Body PATCH :**
```json
{
  "is_active": true,
  "name": "Mort subite modifiée",
  "rules_description": "Nouvelles règles..."
}
```

---

### 5.5 Admin — Questions CRUD

| Méthode | Route | Auth | Description |
|---------|-------|------|-------------|
| GET | `/admin/sessions/{s}/rounds/{r}/questions` | 🔒 Admin | Liste des questions |
| POST | `/admin/sessions/{s}/rounds/{r}/questions` | 🔒 Admin | Créer question |
| GET | `/admin/sessions/{s}/rounds/{r}/questions/{q}` | 🔒 Admin | Détail question |
| PUT | `/admin/sessions/{s}/rounds/{r}/questions/{q}` | 🔒 Admin | Modifier question |
| DELETE | `/admin/sessions/{s}/rounds/{r}/questions/{q}` | 🔒 Admin | Supprimer (status=pending) |

**Body POST (QCM) :**
```json
{
  "text": "Quelle est la capitale de la France ?",
  "answer_type": "qcm",
  "correct_answer": "Paris",
  "duration": 30,
  "media_url": null,
  "media_type": "none",
  "display_order": 1,
  "choices": [
    { "label": "Paris", "is_correct": true, "display_order": 1 },
    { "label": "Lyon", "is_correct": false, "display_order": 2 },
    { "label": "Marseille", "is_correct": false, "display_order": 3 },
    { "label": "Toulouse", "is_correct": false, "display_order": 4 }
  ],
  "hint": {
    "hint_type": "remove_choices",
    "removed_choice_ids": [2, 3],
    "time_penalty_seconds": 5
  }
}
```

**Body POST (Nombre) :**
```json
{
  "text": "Combien de pays en Afrique ?",
  "answer_type": "number",
  "correct_answer": "54",
  "number_is_decimal": false,
  "duration": 20
}
```

**Body POST (Texte) :**
```json
{
  "text": "Quel est le plus grand océan ?",
  "answer_type": "text",
  "correct_answer": "Pacifique",
  "duration": 25
}
```

---

### 5.6 Admin — Questions de Pré-sélection CRUD

| Méthode | Route | Auth |
|---------|-------|------|
| GET | `/admin/sessions/{s}/preselection-questions` | 🔒 Admin |
| POST | `/admin/sessions/{s}/preselection-questions` | 🔒 Admin |
| GET | `/admin/sessions/{s}/preselection-questions/{q}` | 🔒 Admin |
| PUT | `/admin/sessions/{s}/preselection-questions/{q}` | 🔒 Admin |
| DELETE | `/admin/sessions/{s}/preselection-questions/{q}` | 🔒 Admin |

Même structure que les questions normales (texte, answer_type, choices, etc.).

---

### 5.7 Admin — Dashboard

| Méthode | Route | Auth | Description |
|---------|-------|------|-------------|
| GET | `/admin/sessions/{s}/dashboard` | 🔒 Admin | Dashboard temps réel |

**Réponse :** status de la session, jackpot, joueurs restants/actifs/éliminés, manche en cours, question en cours, statistiques de réponses.

---

### 5.8 Admin — Moteur de jeu

Toutes les routes ci-dessous sont `POST` (sauf `next-turn` qui est `GET`), auth 🔒 Admin, préfixe `/admin/sessions/{session}/game/`.

#### Phase pré-jeu

| Route | Pré-condition status | Action |
|-------|---------------------|--------|
| `/open-registration` | `draft` | Passe en `registration_open` |
| `/close-registration` | `registration_open` | Passe en `registration_closed` |
| `/open-preselection` | `registration_closed` | Passe en `preselection` |
| `/select-players` | `preselection` | Sélectionne les joueurs → `ready` |
| `/start` | `ready` | Démarre la session → `in_progress` |

**Body `/select-players` :**
```json
{ "registration_ids": [1, 2, 3, 4, 5, ...] }
```

#### Cycle de question

| Route | Action |
|-------|--------|
| `/launch-question` | Lance une question (body: `{ "question_id": int }`) |
| `/close-question` | Clôture la question en cours |
| `/reveal-answer` | Révèle la bonne réponse (affiche uniquement la réponse correcte) |
| `/show-results` | Affiche les résultats joueurs, éliminations et jackpot |

#### Manche 3 — Seconde chance

| Route | Action |
|-------|--------|
| `/launch-second-chance` | Lance la question de seconde chance |
| `/close-second-chance` | Clôture la seconde chance |
| `/reveal-second-chance` | Révèle la réponse de la seconde chance |
| `/show-sc-results` | Affiche les résultats de la seconde chance |

#### Manche 5 — Top 4

| Route | Action |
|-------|--------|
| `/finalize-top4` | Sélectionne les 4 meilleurs joueurs |

#### Manches 6/7 — Duels

| Route | Méthode | Action |
|-------|---------|--------|
| `/setup-turn-order` | POST | Définit l'ordre de passage |
| `/next-turn` | GET | Retourne le prochain joueur |

#### Manche 8 — Finale

| Route | Action |
|-------|--------|
| `/reveal-finale-choices` | Révèle les choix des finalistes |
| `/resolve-finale` | Calcule et applique le résultat final |

#### Navigation

| Route | Action |
|-------|--------|
| `/next-round` | Passe à la manche suivante |
| `/end` | Termine la session |

---

### 5.9 Joueur — Routes publiques

| Méthode | Route | Auth | Body |
|---------|-------|------|------|
| POST | `/player/register` | ❌ | `{ session_id, full_name, email, phone, pseudo }` |
| GET | `/player/registrations/{id}` | ❌ | — |
| GET | `/player/sessions/{s}/preselection/questions` | ❌ | — |
| POST | `/player/preselection/submit` | ❌ | Voir ci-dessous |

**Body inscription :**
```json
{
  "session_id": 1,
  "full_name": "John Doe",
  "email": "john@example.com",
  "phone": "+33612345678",
  "pseudo": "JohnDoe123"
}
```

**Body soumission pré-sélection :**
```json
{
  "registration_token": "abc123xyz",
  "answers": [
    {
      "preselection_question_id": 1,
      "answer_value": "54",
      "response_time_ms": 5000
    },
    {
      "preselection_question_id": 2,
      "selected_choice_id": 3,
      "response_time_ms": 3000
    }
  ]
}
```

---

### 5.10 Joueur — Routes protégées (player.token)

| Méthode | Route | Body | Description |
|---------|-------|------|-------------|
| POST | `/player/join` | — | Rejoindre la salle de jeu |
| GET | `/player/status` | — | Statut actuel du joueur |
| POST | `/player/answer` | Voir ci-dessous | Soumettre une réponse |
| POST | `/player/hint` | — | Utiliser l'indice (Manche 2 uniquement) |
| POST | `/player/pass-manche` | — | Passer la manche (Manche 4, coûte 1000) |
| POST | `/player/finale-choice` | `{ "choice": "continue" \| "abandon" }` | Choix finale |

**Body `/player/answer` :**
```json
{
  "question_id": 5,
  "answer_value": "Paris",                  // pour texte/nombre
  "selected_choice_id": 2,                  // pour QCM
  "response_time_ms": 8500,
  "is_second_chance": false,                // optionnel
  "second_chance_question_id": null,        // optionnel
  "selected_sc_choice_id": null             // optionnel, pour QCM seconde chance
}
```

---

### 5.11 Projection

| Méthode | Route | Auth | Body/Params |
|---------|-------|------|-------------|
| POST | `/admin/sessions/{s}/projection/generate` | 🔒 Admin | — (génère un code 6 chars) |
| POST | `/projection/authenticate` | ❌ | `{ "access_code": "ABC123" }` |
| GET | `/projection/{accessCode}/sync` | ❌ | — (état complet de la session) |

---

## 6. Formats de réponse API (Resources)

### SessionResource

```typescript
interface Session {
  id: number;
  name: string;
  description: string | null;
  cover_image_url: string | null;
  scheduled_at: string;          // ISO 8601
  max_players: number;
  status: SessionStatus;
  registration_opens_at: string | null;
  registration_closes_at: string | null;
  preselection_opens_at: string | null;
  preselection_closes_at: string | null;
  jackpot: number;
  players_remaining: number;
  reconnection_delay: number;
  projection_code: string;
  started_at: string | null;
  ended_at: string | null;
  created_at: string;
  rounds_count: number;
  registrations_count: number;
  rounds?: SessionRound[];
  current_round?: SessionRound;
}
```

### SessionRoundResource

```typescript
interface SessionRound {
  id: number;
  session_id: number;
  round_number: number;
  round_type: RoundType;
  display_order: number;
  is_active: boolean;
  status: RoundStatus;
  started_at: string | null;
  ended_at: string | null;
  questions_count?: number;
  questions?: Question[];
}
```

### QuestionResource

```typescript
interface Question {
  id: number;
  session_round_id: number;
  text: string;
  answer_type: AnswerType;
  correct_answer?: string;       // uniquement visible par l'admin
  number_is_decimal: boolean;
  duration: number;              // en secondes
  display_order: number;
  media_url: string | null;
  media_type: MediaType;
  status: QuestionStatus;
  launched_at: string | null;
  closed_at: string | null;
  choices?: QuestionChoice[];
  hint?: QuestionHint;           // uniquement admin
}

interface QuestionChoice {
  id: number;
  label: string;
  is_correct?: boolean;          // uniquement admin
  display_order: number;
}

interface QuestionHint {
  hint_type: HintType;
  time_penalty_seconds: number;
  removed_choice_ids?: number[];
  revealed_letters?: string[];
  range_hint_text?: string;
  range_min?: number;
  range_max?: number;
}
```

### RegistrationResource

```typescript
interface Registration {
  id: number;
  session_id: number;
  player_id: number;
  status: RegistrationStatus;
  registered_at: string;
  player?: {
    full_name: string;
    email: string;
    phone: string;
    pseudo: string;
  };
  preselection_result?: {
    correct_answers_count: number;
    total_questions: number;
    total_response_time_ms: number;
    rank: number;
    is_selected: boolean;
  };
}
```

### SessionPlayerResource

```typescript
interface SessionPlayer {
  id: number;
  session_id: number;
  player_id: number;
  status: SessionPlayerStatus;
  capital: number;
  personal_jackpot: number;
  final_gain: number;
  is_connected: boolean;
  eliminated_at: string | null;
  elimination_reason: string | null;
  eliminated_in_round_id: number | null;
  player?: Player;
}

interface Player {
  id: number;
  full_name: string;
  email: string;
  pseudo: string;
  phone: string;
  avatar_url: string | null;
  created_at: string;
}
```

### PreselectionQuestionResource

```typescript
interface PreselectionQuestion {
  id: number;
  text: string;
  answer_type: AnswerType;
  duration: number;
  display_order: number;
  media_url: string | null;
  media_type: MediaType;
  choices?: {
    id: number;
    label: string;
    display_order: number;
  }[];
}
```

---

## 7. WebSocket — Événements temps réel

### 7.1 Channels

| Channel | Type | Usage |
|---------|------|-------|
| `session.{sessionId}` | Public | Tous les événements globaux du jeu |
| `player.{sessionPlayerId}` | Privé | Résultats individuels du joueur |

### 7.2 Écoute côté client

```typescript
// Événements publics (session)
echo.channel(`session.${sessionId}`)
  .listen('.question.launched', (data) => { ... })
  .listen('.answer.revealed', (data) => { ... })
  .listen('.player.eliminated', (data) => { ... })
  .listen('.jackpot.updated', (data) => { ... })
  .listen('.round.started', (data) => { ... })
  .listen('.round.ended', (data) => { ... })
  .listen('.question.closed', (data) => { ... })
  .listen('.timer.tick', (data) => { ... })
  .listen('.timer.expired', (data) => { ... })
  .listen('.game.ended', (data) => { ... });

// Événements privés (joueur)
echo.private(`player.${sessionPlayerId}`)
  .listen('.answer.result', (data) => { ... })
  .listen('.hint.applied', (data) => { ... });
```

### 7.3 Payloads des événements

#### `question.launched` — Canal: `session.{id}`
Quand l'admin lance une question.
```json
{
  "question": {
    "id": 5,
    "text": "Quelle est la capitale de la France ?",
    "answer_type": "qcm",
    "media_url": null,
    "media_type": "none",
    "duration": 30,
    "launched_at": "2026-03-05T20:15:30+00:00",
    "choices": [
      { "id": 1, "label": "Paris", "display_order": 1 },
      { "id": 2, "label": "Lyon", "display_order": 2 },
      { "id": 3, "label": "Marseille", "display_order": 3 }
    ]
  }
}
```

#### `answer.result` — Canal: `player.{id}` (privé)
Résultat individuel après soumission.
```json
{
  "question_id": 5,
  "is_correct": true,
  "correct_answer": "Paris"
}
```

#### `answer.revealed` — Canal: `session.{id}`
L'admin révèle la bonne réponse (étape 2 — affiche uniquement la réponse, sans résultats joueurs).
```json
{
  "question_id": 5,
  "correct_answer": "Paris",
  "choices": [
    { "id": 1, "label": "Paris", "is_correct": true },
    { "id": 2, "label": "Lyon", "is_correct": false },
    { "id": 3, "label": "Marseille", "is_correct": false }
  ]
}
```

#### `results.revealed` — Canal: `session.{id}`
L'admin affiche les résultats (étape 3 — après reveal). Déclenche aussi `player.eliminated` et `jackpot.updated`.
```json
{
  "question_id": 5,
  "player_results": [
    { "pseudo": "Player1", "is_correct": true, "is_timeout": false },
    { "pseudo": "Player2", "is_correct": false, "is_timeout": false },
    { "pseudo": "Player3", "is_correct": false, "is_timeout": true }
  ]
}
```

#### `second_chance.revealed` — Canal: `session.{id}`
L'admin révèle la réponse de la seconde chance (affiche uniquement la réponse).
```json
{
  "main_question_id": 5,
  "correct_answer": "Paris",
  "choices": [
    { "id": 10, "label": "Paris", "is_correct": true },
    { "id": 11, "label": "Lyon", "is_correct": false }
  ]
}
```

#### `sc_results.revealed` — Canal: `session.{id}`
L'admin affiche les résultats de la seconde chance.
```json
{
  "main_question_id": 5,
  "player_results": [
    { "pseudo": "Player2", "is_correct": true, "is_timeout": false },
    { "pseudo": "Player3", "is_correct": false, "is_timeout": true }
  ]
}
```

#### `question.closed` — Canal: `session.{id}`
Quand le timer expire ou l'admin clôture.
```json
{
  "question_id": 5,
  "answers_received": 45,
  "correct_count": 32,
  "eliminated_count": 13
}
```

#### `player.eliminated` — Canal: `session.{id}`
Après les éliminations.
```json
{
  "eliminated": [
    { "pseudo": "Player42", "reason": "wrong_answer" },
    { "pseudo": "GamerX", "reason": "timeout" }
  ],
  "players_remaining": 35,
  "jackpot": 15000
}
```

#### `jackpot.updated` — Canal: `session.{id}`
Mise à jour de la cagnotte.
```json
{
  "jackpot": 23000,
  "players_remaining": 27
}
```

#### `round.started` — Canal: `session.{id}`
Début d'une nouvelle manche.
```json
{
  "round_number": 2,
  "name": "Utilisation d'indice",
  "round_type": "hint",
  "rules_description": "Vous pouvez utiliser un indice une seule fois..."
}
```

#### `round.ended` — Canal: `session.{id}`
Fin d'une manche.
```json
{
  "round_number": 1,
  "name": "Mort subite",
  "players_remaining": 45,
  "jackpot": 55000
}
```

#### `timer.tick` — Canal: `session.{id}`
Décompte chaque seconde.
```json
{
  "question_id": 5,
  "remaining_seconds": 15
}
```

#### `timer.expired` — Canal: `session.{id}`
Fin du timer.
```json
{
  "question_id": 5
}
```

#### `hint.applied` — Canal: `player.{id}` (privé)
Indice appliqué au joueur.
```json
{
  "question_id": 5,
  "hint": {
    "hint_type": "remove_choices",
    "removed_choice_ids": [2, 3],
    "time_penalty_seconds": 5
  }
}
```

#### `game.ended` — Canal: `session.{id}`
Fin du jeu avec résultats.
```json
{
  "final_jackpot": 98000,
  "winners": [
    { "pseudo": "Champion1", "final_gain": 49000 },
    { "pseudo": "Champion2", "final_gain": 49000 }
  ]
}
```

---

## 8. Enums et statuts

### SessionStatus
```typescript
type SessionStatus =
  | 'draft'               // Brouillon, configuration en cours
  | 'registration_open'   // Inscriptions ouvertes
  | 'registration_closed' // Inscriptions fermées
  | 'preselection'        // Test de pré-sélection en cours
  | 'ready'               // Joueurs sélectionnés, prêt à démarrer
  | 'in_progress'         // Jeu en cours
  | 'paused'              // En pause
  | 'ended'               // Terminé
  | 'cancelled';          // Annulé
```

### SessionPlayerStatus
```typescript
type SessionPlayerStatus =
  | 'waiting'             // En attente du début
  | 'active'              // En jeu
  | 'eliminated'          // Éliminé
  | 'finalist'            // Finaliste (top 4 → top 2)
  | 'finalist_winner'     // Gagnant de la finale
  | 'finalist_loser'      // Perdant de la finale
  | 'abandoned';          // A abandonné
```

### RoundType
```typescript
type RoundType =
  | 'sudden_death'        // Manche 1 — Mort subite
  | 'hint'                // Manche 2 — Avec indice
  | 'second_chance'       // Manche 3 — Seconde chance
  | 'round_skip'          // Manche 4 — Passage de manche
  | 'top4_elimination'    // Manche 5 — Top 4
  | 'duel_jackpot'        // Manche 6 — Duel cagnotte
  | 'duel_elimination'    // Manche 7 — Duel élimination
  | 'finale';             // Manche 8 — Finale
```

### RoundStatus
```typescript
type RoundStatus =
  | 'pending'             // Pas encore commencé
  | 'in_progress'         // En cours
  | 'completed'           // Terminé
  | 'skipped';            // Sauté (désactivé par l'admin)
```

### QuestionStatus
```typescript
type QuestionStatus =
  | 'pending'             // Prête à être lancée
  | 'launched'            // En cours (timer actif)
  | 'closed'              // Fermée (réponses évaluées)
  | 'revealed';           // Bonne réponse révélée
```

### AnswerType
```typescript
type AnswerType =
  | 'qcm'                // Question à choix multiples
  | 'number'             // Réponse numérique
  | 'text';              // Texte libre
```

### MediaType
```typescript
type MediaType =
  | 'none'               // Pas de média
  | 'image'              // Image
  | 'video'              // Vidéo
  | 'audio';             // Audio
```

### RegistrationStatus
```typescript
type RegistrationStatus =
  | 'registered'              // Inscrit
  | 'preselection_pending'    // En attente du test
  | 'preselection_done'       // Test terminé
  | 'selected'                // Sélectionné pour le jeu
  | 'rejected';               // Non retenu
```

### EliminationReason
```typescript
type EliminationReason =
  | 'wrong_answer'            // Mauvaise réponse
  | 'timeout'                 // Temps écoulé
  | 'second_chance_failed'    // Échec seconde chance
  | 'round_skip'              // Passage de manche
  | 'top4_cutoff'             // Non retenu au top 4
  | 'duel_lost'               // Duel perdu
  | 'finale_lost'             // Finale perdue
  | 'manual';                 // Élimination manuelle admin
```

### HintType
```typescript
type HintType =
  | 'remove_choices'     // QCM : retirer des propositions
  | 'reveal_letters'     // Texte : révéler des lettres
  | 'reduce_range';      // Nombre : réduire l'intervalle
```

### FinaleChoiceType
```typescript
type FinaleChoiceType = 'continue' | 'abandon';
```

### FinaleScenario
```typescript
type FinaleScenario =
  | 'both_continue_both_win'    // 2 continuent, 2 réussissent → partage
  | 'both_continue_one_wins'    // 2 continuent, 1 réussit → tout au gagnant
  | 'both_continue_both_fail'   // 2 continuent, 2 échouent → 2000 chacun
  | 'one_abandons'              // 1 abandonne → abandonneur: 2000, l'autre joue seul
  | 'both_abandon';             // 2 abandonnent → 5000 chacun
```

---

## 9. Flow complet du jeu

### 9.1 Flow Admin (pilotage)

```
1. Créer une session (POST /admin/sessions)
2. Configurer les manches (PATCH /rounds/{id})
3. Ajouter les questions à chaque manche (POST /questions)
4. Ajouter les questions de pré-sélection
5. Ouvrir les inscriptions (POST /game/open-registration)
6. [Attendre les inscriptions]
7. Fermer les inscriptions (POST /game/close-registration)
8. Ouvrir la pré-sélection (POST /game/open-preselection)
9. [Attendre que les joueurs passent le test]
10. Sélectionner les joueurs (POST /game/select-players)
11. Démarrer la session (POST /game/start)

--- BOUCLE DE JEU ---
Pour chaque manche active :
  12. [round.started émis automatiquement]
  Pour chaque question :
    13. Lancer la question (POST /game/launch-question)
    14. [timer.tick émis chaque seconde]
    15. [Attendre les réponses / timer expire]
    16. Clôturer la question (POST /game/close-question)
    17. Révéler la réponse (POST /game/reveal-answer) → affiche uniquement la bonne réponse
    18. Afficher les résultats (POST /game/show-results) → éliminés, jackpot, résultats joueurs
  19. Passer à la manche suivante (POST /game/next-round)

--- PHASES SPÉCIALES ---
Manche 3 : launch-second-chance → close-second-chance → reveal-second-chance → show-sc-results
Manche 5 : finalize-top4
Manche 6/7 : setup-turn-order → boucle next-turn
Manche 8 : reveal-finale-choices → resolve-finale

20. Terminer le jeu (POST /game/end)
```

### 9.2 Flow Joueur

```
1. S'inscrire (POST /player/register)
2. Passer le test de pré-sélection (POST /player/preselection/submit)
3. [Attendre la sélection]
4. Recevoir le lien unique (access_token par email)
5. Rejoindre la salle (POST /player/join)
6. [Écouter les événements WebSocket]

--- BOUCLE DE JEU ---
7. Recevoir round.started → afficher info manche
8. Recevoir question.launched → afficher question + démarrer timer
9. Soumettre réponse (POST /player/answer)
10. Recevoir answer.result (privé) → correct/incorrect
11. Recevoir question.closed → stats
12. Recevoir answer.revealed → bonne réponse
13. Recevoir player.eliminated → si éliminé, afficher notification

--- ACTIONS SPÉCIALES ---
Manche 2 : POST /player/hint (une fois par manche)
Manche 4 : POST /player/pass-manche
Manche 8 : POST /player/finale-choice

14. Recevoir game.ended → résultat final
```

### 9.3 Flow Projection

```
1. Authentifier avec le code (POST /projection/authenticate)
2. Synchroniser l'état (GET /projection/{code}/sync)
3. Écouter session.{id} via WebSocket
4. Réagir visuellement à chaque événement :
   - round.started → animation intro manche
   - question.launched → afficher question + timer
   - timer.tick → décompte visuel
   - answer.revealed → afficher bonne réponse
   - player.eliminated → liste des éliminés
   - jackpot.updated → animation cagnotte
   - round.ended → récapitulatif
   - game.ended → résultat final + gagnants
```

---

## 10. Détail des 8 manches

### Manche 1 — Mort subite (`sudden_death`)
- **Règle** : Mauvaise réponse = élimination immédiate
- **Timeout** : éliminé
- **UI joueur** : Question + choix/input + timer
- **UI projection** : Question + timer + liste éliminés après chaque Q

### Manche 2 — Indice (`hint`)
- **Règle** : Comme mort subite + 1 indice utilisable une seule fois dans la manche
- **Bouton joueur** : « Utiliser mon indice » (visible si pas encore utilisé)
- **Après activation** : modification de la question (retrait choix / lettres révélées / intervalle) + pénalité temps
- **UI** : Bouton indice grisé après usage

### Manche 3 — Seconde chance (`second_chance`)
- **Étape 1** : Question principale → les joueurs répondent
- **Étape 2** : Joueurs corrects → attente. Joueurs en échec → question de rattrapage
- **Étape 3** : L'admin lance la seconde chance
- **Étape 4** : Échec seconde chance = éliminé définitivement
- **UI joueur en attente** : « En attente de la seconde chance... »
- **UI joueur en seconde chance** : Bordure orange, icône alerte

### Manche 4 — Passage de manche (`round_skip`)
- **Règle** : Le joueur peut « passer sa manche » → perd 1000 mais reste en jeu
- **Mauvaise réponse** : éliminé définitivement
- **UI joueur** : Bouton « Passer ma manche » visible
- **Dilemme stratégique** : risquer de répondre vs payer 1000

### Manche 5 — Top 4 (`top4_elimination`)
- **Règle** : Classement final basé sur (1) bonnes réponses ↓ puis (2) temps cumulé ↑
- **Top 4** gardés, le reste éliminé
- **Pas d'élimination après chaque question** — classement à la fin
- **UI** : Classement provisoire visible

### Manche 6 — Duel Cagnotte (`duel_jackpot`)
- **4 joueurs, tour par tour**
- L'admin définit l'ordre de passage
- Un seul joueur répond à la fois, les autres regardent
- **Bonne réponse** : +1000 ajoutés à sa cagnotte personnelle
- **Mauvaise réponse / timeout** : éliminé, repart avec sa cagnotte personnelle accumulée
- **Rotation continue** : les éliminés sont sautés
- Continue jusqu'à ce qu'il reste 2 joueurs (ou selon les règles)
- **UI** : Indicateur « C'est votre tour ! » / « En attente du tour de [pseudo]... »

### Manche 7 — Duel Élimination (`duel_elimination`)
- **Tour par tour** entre les joueurs restants
- Premier à donner une mauvaise réponse = éliminé
- **Résultat** : 2 finalistes
- **UI** : Similaire à manche 6

### Manche 8 — Finale (`finale`)
- **Étape 1 — Choix** : Chaque finaliste choisit « Continuer » ou « Abandonner » (simultané, secret)
- **Étape 2 — Révélation** : Les choix sont révélés
- **Étape 3 — Question finale** : Posée aux joueurs ayant choisi « Continuer »
- **Étape 4 — Résultat** : Répartition des gains selon le scénario

#### Scénarios de gains

| Scénario | Condition | Résultat |
|----------|-----------|---------|
| Les 2 continuent + 2 réussissent | `both_continue_both_win` | Cagnotte / 2 chacun |
| Les 2 continuent + 1 réussit | `both_continue_one_wins` | Tout au gagnant |
| Les 2 continuent + 2 échouent | `both_continue_both_fail` | 2000 chacun |
| 1 abandonne | `one_abandons` | Abandonneur: 2000, l'autre joue seul |
| Les 2 abandonnent | `both_abandon` | 5000 chacun |

---

## 11. Système de cagnotte

### Formule

```
Cagnotte = (Joueurs éliminés × 1000) + (Passages manche 4 × 1000)
```

### Capital initial
Chaque joueur entre avec **1000 points**.

### Alimentation
- Élimination (manches 1-5) : +1000 à la cagnotte
- Passage de manche (manche 4) : +1000 à la cagnotte (joueur non éliminé)

### Gains manche 6
- Bonne réponse : +1000 à la cagnotte personnelle du joueur
- Élimination en manche 6 : repart avec sa cagnotte perso accumulée

### Transactions de cagnotte (types)
```typescript
type JackpotTransactionType =
  | 'elimination'              // +1000 par joueur éliminé
  | 'round_skip'               // +1000 passage de manche
  | 'round6_bonus'             // +1000 bonne réponse manche 6
  | 'round6_departure'         // Joueur repart avec sa cagnotte
  | 'finale_win'               // Gain finale
  | 'finale_share'             // Partage finale
  | 'finale_abandon_share'     // Partage si abandon
  | 'manual_adjustment';       // Ajustement admin
```

---

## 12. Spécifications UI par interface

### 12.1 Interface Admin

#### Pages

| Page | Route Next.js | Fonctionnalité |
|------|---------------|----------------|
| Login | `/admin/login` | Formulaire email/password |
| Liste sessions | `/admin/sessions` | Tableau des sessions avec stats |
| Créer session | `/admin/sessions/create` | Formulaire de création |
| Détail session | `/admin/sessions/[id]` | Vue d'ensemble, configuration |
| Manches | `/admin/sessions/[id]/rounds` | Configuration des 8 manches |
| Questions | `/admin/sessions/[id]/rounds/[roundId]/questions` | CRUD questions |
| Pré-sélection | `/admin/sessions/[id]/preselection` | Questions + résultats |
| Pilotage jeu | `/admin/sessions/[id]/game` | Interface live de pilotage |
| Dashboard | `/admin/sessions/[id]/dashboard` | Métriques en direct |

#### Interface de pilotage (page la plus critique)

```
┌──────────────────────────────────────────────────────┐
│  LOGIK GAME — Session: LOGIK S01E01    [En cours]    │
├──────────────────┬───────────────────────────────────┤
│                  │                                   │
│  Manche: 1/8     │  Question 3/10                   │
│  Mort subite     │  "Quelle est la capitale..."     │
│                  │                                   │
│  Joueurs: 48     │  Timer: 15s                      │
│  Cagnotte: 12000 │                                   │
│                  │  Réponses: 42/48                  │
│  ──────────────  │  Correctes: 35                   │
│                  │  Incorrectes: 7                   │
│  [Lancer Q]      │                                   │
│  [Clôturer Q]    │  ──────────────────              │
│  [Révéler]       │                                   │
│  [Manche suiv.]  │  Derniers éliminés:              │
│  [Terminer]      │  • Player42 (mauvaise rép.)       │
│                  │  • GamerX (timeout)               │
└──────────────────┴───────────────────────────────────┘
```

### 12.2 Interface Joueur

#### Pages

| Page | Route Next.js | Fonctionnalité |
|------|---------------|----------------|
| Inscription | `/player/register` | Formulaire d'inscription |
| Pré-sélection | `/player/preselection/[sessionId]` | Test de pré-sélection |
| Salle d'attente | `/player/game/waiting` | En attente du début |
| Jeu | `/player/game` | Interface de jeu temps réel |
| Éliminé | `/player/game/eliminated` | Écran post-élimination |
| Résultat | `/player/game/result` | Résultat final |

#### États de l'interface joueur

```
ATTENTE         → Écran : "La manche va bientôt commencer..."
QUESTION        → Question affichée + zone de réponse + timer
RÉPONDU         → "Réponse soumise, en attente..."
RÉSULTAT        → ✅ Correct / ❌ Incorrect
ÉLIMINÉ         → "Vous êtes éliminé. 1000 → cagnotte"
SECONDE CHANCE  → Question de rattrapage (bordure orange)
EN ATTENTE SC   → "Les joueurs en échec passent la seconde chance..."
```

#### Composants clés joueur

- **Timer** : décompte circulaire ou barre de progression
- **QCM** : grille de boutons, clic = soumission définitive
- **Input Nombre** : clavier numérique, bouton valider
- **Input Texte** : champ texte, bouton valider
- **Bouton Indice** : visible uniquement en manche 2 (grisé après usage)
- **Bouton Passer** : visible uniquement en manche 4
- **Score** : capital + cagnotte en header

### 12.3 Interface Projection

#### Caractéristiques

- **Plein écran** (1920×1080 min)
- **Aucun élément interactif** (lecture seule)
- **Polices grandes** (lisibles à distance)
- **Animations** pour les transitions

#### Écrans de la projection

| État | Contenu affiché |
|------|-----------------|
| Attente | Logo + nom de la session |
| Intro manche | Numéro + nom + règles (quelques secondes) |
| Question | Texte + média en plein écran |
| Timer | Décompte en grand format |
| Réponse révélée | Bonne réponse mise en évidence |
| Éliminés | Liste des pseudos éliminés + animation |
| Cagnotte | Montant avec animation de mise à jour |
| Transition manche | Récapitulatif + intro suivante |
| Finale | Choix des finalistes + résultat |
| Fin de jeu | Gagnant(s) + montant final |

---

## 13. Gestion des erreurs et edge cases

### Déconnexion joueur
- Délai de grâce configurable (`reconnection_delay`, défaut 10s)
- Si pas reconnecté : réponse comptée comme absente
- État préservé côté serveur pour reconnexion

### Timer
- Géré **côté serveur** (équité garantie)
- Le client affiche un timer local synchronisé via `timer.tick`
- Si aucune réponse avant `timer.expired` → timeout

### Anti-triche
- Réponse unique par question
- Horodatage serveur (pas client)
- Token unique par joueur

### Erreurs API courantes

| HTTP Code | Signification | Action frontend |
|-----------|---------------|-----------------|
| 401 | Token invalide/expiré | Rediriger vers login |
| 403 | Action non autorisée (mauvais status) | Afficher message |
| 404 | Ressource introuvable | Afficher 404 |
| 422 | Validation échouée | Afficher les erreurs sous les champs |
| 429 | Rate limited | Afficher "Trop de requêtes" |
| 500 | Erreur serveur | Afficher erreur générique |

### Format d'erreur API Laravel

```json
{
  "message": "Le nom de la session est obligatoire.",
  "errors": {
    "name": ["Le nom de la session est obligatoire."],
    "scheduled_at": ["La date est obligatoire."]
  }
}
```

---

> **Ce document est la référence complète pour le développement frontend Next.js de LOGIK GAME.**
> Toutes les routes, payloads, événements WebSocket, types TypeScript et flows sont documentés.
