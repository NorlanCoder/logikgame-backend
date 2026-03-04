# LOGIK GAME — Backend API

API REST pour un jeu télévisé de quiz en temps réel, avec WebSocket et notifications email.

**Stack :** Laravel 12 · PHP 8.3 · MySQL · Sanctum · Reverb · Pest 4

---

## Installation

```bash
git clone https://github.com/NorlanCoder/logikgame-backend.git
cd logikgame-backend

composer install

cp .env.example .env
php artisan key:generate
```

Configurer `.env` (DB, mail, Reverb), puis :

```bash
php artisan migrate --seed
```

---

## Lancer les services

```bash
php artisan serve                  # API (port 8000)
php artisan reverb:start           # WebSocket (port 8080)
php artisan queue:work             # Notifications email
```

---

## Documentation API

Disponible sur `/api/documentation` après `php artisan l5-swagger:generate`.

---

## Tests

```bash
php artisan test --compact
```

86 tests · 207 assertions.

---

## Authentification

| Rôle | Mécanisme |
|------|-----------|
| Admin | Bearer token (Sanctum) — `Authorization: Bearer {token}` |
| Joueur | Header personnalisé — `X-Player-Token: {token}` |
| Projection | Code d'accès 6 caractères |

---

## Structure des manches

| # | Type | Règle |
|---|------|-------|
| 1 | Classique | Mauvaise réponse = élimination |
| 2 | Indice | 1 joker utilisable par joueur |
| 3 | Seconde chance | Seconde chance sur échec |
| 4 | Passage | Payer 1 000 pour passer |
| 5 | Top 4 | Classement — seuls les 4 meilleurs restent |
| 6 | Duel Jackpot | Duels à tour de rôle, cagnotte perso |
| 7 | Duel Élimination | Duels à tour de rôle, élimination directe |
| 8 | Finale | Continuer ou abandonner |
