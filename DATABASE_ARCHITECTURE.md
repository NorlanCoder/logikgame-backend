# Architecture de la Base de Données — LOGIK GAME

## Table des matières

1. [Vue d'ensemble](#1-vue-densemble)
2. [Diagramme Entité-Relation (textuel)](#2-diagramme-entité-relation)
3. [Tables détaillées](#3-tables-détaillées)
   - 3.1 [admins](#31-admins)
   - 3.2 [players](#32-players)
   - 3.3 [sessions](#33-sessions)
   - 3.4 [session_rounds](#34-session_rounds)
   - 3.5 [questions](#35-questions)
   - 3.6 [question_choices](#36-question_choices)
   - 3.7 [question_hints](#37-question_hints)
   - 3.8 [second_chance_questions](#38-second_chance_questions)
   - 3.9 [registrations](#39-registrations)
   - 3.10 [preselection_questions](#310-preselection_questions)
   - 3.11 [preselection_question_choices](#311-preselection_question_choices)
   - 3.12 [preselection_answers](#312-preselection_answers)
   - 3.13 [preselection_results](#313-preselection_results)
   - 3.14 [session_players](#314-session_players)
   - 3.15 [player_answers](#315-player_answers)
   - 3.16 [hint_usages](#316-hint_usages)
   - 3.17 [round_skips](#317-round_skips)
   - 3.18 [eliminations](#318-eliminations)
   - 3.19 [jackpot_transactions](#319-jackpot_transactions)
   - 3.20 [round_rankings](#320-round_rankings)
   - 3.21 [finale_choices](#321-finale_choices)
   - 3.22 [final_results](#322-final_results)
   - 3.23 [round6_turn_order](#323-round6_turn_order)
   - 3.24 [round6_player_jackpots](#324-round6_player_jackpots)
   - 3.25 [session_events](#325-session_events)
   - 3.26 [player_connections](#326-player_connections)
   - 3.27 [projection_accesses](#327-projection_accesses)
4. [Index recommandés](#4-index-recommandés)
5. [Contraintes et règles métier](#5-contraintes-et-règles-métier)
6. [Notes sur les migrations Laravel](#6-notes-sur-les-migrations-laravel)

---

## 1. Vue d'ensemble

La base de données MySQL de LOGIK GAME couvre l'ensemble du cycle de vie d'une session de jeu :

- **Utilisateurs** : administrateurs et joueurs avec authentification différenciée
- **Sessions** : configuration complète d'une partie (manches, questions, paramètres)
- **Pré-sélection** : inscriptions, test de pré-sélection, classement
- **Jeu en temps réel** : réponses, éliminations, indices, seconde chance
- **Scoring** : cagnotte, transactions, gains finaux
- **Audit & temps réel** : événements, connexions, projections

**Moteur** : InnoDB (transactions ACID)
**Encodage** : utf8mb4 (support complet Unicode / emojis)
**Collation** : utf8mb4_unicode_ci

---

## 2. Diagramme Entité-Relation

```
admins ─────────────────┐
                        │ 1:N
                        ▼
                    sessions ◄──────────── projection_accesses
                        │
          ┌─────────────┼───────────────────────────────┐
          │ 1:N         │ 1:N                           │ 1:N
          ▼             ▼                               ▼
   session_rounds   registrations              preselection_questions
          │             │                               │
          │ 1:N         │ 1:1                           │ 1:N
          ▼             ▼                               ▼
      questions    preselection_results      preselection_question_choices
          │             │                               │
    ┌─────┼─────┐       │                               │
    │     │     │       ▼                               ▼
    │     │     │  session_players ◄───── preselection_answers
    │     │     │       │
    │     │     │  ┌────┼──────────┬──────────┬──────────┐
    │     │     │  │    │          │          │          │
    ▼     ▼     ▼  ▼    ▼          ▼          ▼          ▼
 question  question  second_chance  player   hint     round    eliminations
 _choices  _hints    _questions    _answers  _usages  _skips
                                    │
                                    ▼
                            jackpot_transactions
                            round_rankings
                            round6_turn_order
                            round6_player_jackpots
                            finale_choices
                            final_results
                            session_events
                            player_connections
```

---

## 3. Tables détaillées

### 3.1 `admins`

> Administrateurs (Game Masters) de l'application.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `name` — VARCHAR(255), NOT NULL — Nom complet de l'administrateur
- `email` — VARCHAR(255), NOT NULL — Adresse email (unique)
- `password` — VARCHAR(255), NOT NULL — Mot de passe hashé (bcrypt)
- `avatar` — VARCHAR(500), NULLABLE — URL de la photo de profil
- `is_active` — BOOLEAN, NOT NULL, défaut : TRUE — Compte actif ou désactivé
- `last_login_at` — TIMESTAMP, NULLABLE — Dernière connexion
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `UNIQUE (email)`

---

### 3.2 `players`

> Joueurs inscrits sur la plateforme. Un joueur peut participer à plusieurs sessions.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `full_name` — VARCHAR(255), NOT NULL — Nom complet
- `email` — VARCHAR(255), NOT NULL — Adresse email (unique)
- `phone` — VARCHAR(20), NOT NULL — Numéro de téléphone
- `pseudo` — VARCHAR(100), NOT NULL — Nom de jeu / pseudo
- `avatar_url` — VARCHAR(500), NULLABLE — URL de la photo de profil
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `UNIQUE (email)`
- `UNIQUE (pseudo)`

---

### 3.3 `sessions`

> Instance complète d'un jeu LOGIK GAME.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `admin_id` — BIGINT UNSIGNED, NOT NULL — FK → admins.id
- `name` — VARCHAR(255), NOT NULL — Nom de la session (ex: « LOGIK S01E03 »)
- `description` — TEXT, NULLABLE — Description publique
- `cover_image_url` — VARCHAR(500), NULLABLE — URL de l'image de couverture
- `scheduled_at` — DATETIME, NOT NULL — Date et heure prévues du lancement
- `max_players` — INT UNSIGNED, NOT NULL — Nombre maximum de joueurs sélectionnés
- `status` — ENUM('draft', 'registration_open', 'registration_closed', 'preselection', 'ready', 'in_progress', 'paused', 'ended', 'cancelled'), NOT NULL, défaut : 'draft' — État de la session
- `registration_opens_at` — DATETIME, NULLABLE — Ouverture des inscriptions
- `registration_closes_at` — DATETIME, NULLABLE — Clôture des inscriptions
- `preselection_opens_at` — DATETIME, NULLABLE — Début du test de pré-sélection
- `preselection_closes_at` — DATETIME, NULLABLE — Fin du test de pré-sélection
- `current_round_id` — BIGINT UNSIGNED, NULLABLE — FK → session_rounds.id (manche en cours)
- `current_question_id` — BIGINT UNSIGNED, NULLABLE — FK → questions.id (question en cours)
- `jackpot` — INT UNSIGNED, NOT NULL, défaut : 0 — Montant actuel de la cagnotte
- `players_remaining` — INT UNSIGNED, NOT NULL, défaut : 0 — Nombre de joueurs encore en jeu
- `reconnection_delay` — INT UNSIGNED, NOT NULL, défaut : 10 — Délai de grâce en secondes pour reconnexion
- `projection_code` — VARCHAR(10), NULLABLE — Code d'accès pour l'écran de projection
- `started_at` — DATETIME, NULLABLE — Heure effective de début
- `ended_at` — DATETIME, NULLABLE — Heure effective de fin
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE RESTRICT`
- `FOREIGN KEY (current_round_id) REFERENCES session_rounds(id) ON DELETE SET NULL`
- `FOREIGN KEY (current_question_id) REFERENCES questions(id) ON DELETE SET NULL`
- `INDEX (status)`
- `INDEX (scheduled_at)`

---

### 3.4 `session_rounds`

> Manches configurées pour une session. Chaque session a jusqu'à 8 manches.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_id` — BIGINT UNSIGNED, NOT NULL — FK → sessions.id
- `round_number` — TINYINT UNSIGNED, NOT NULL — Numéro de la manche (1 à 8)
- `round_type` — ENUM('sudden_death', 'hint', 'second_chance', 'round_skip', 'top4_elimination', 'duel_jackpot', 'duel_elimination', 'finale'), NOT NULL — Type de mécanique
- `name` — VARCHAR(100), NOT NULL — Nom affiché (ex: « Mort subite »)
- `is_active` — BOOLEAN, NOT NULL, défaut : TRUE — Manche activée dans la configuration
- `status` — ENUM('pending', 'in_progress', 'completed', 'skipped'), NOT NULL, défaut : 'pending' — État de la manche
- `display_order` — TINYINT UNSIGNED, NOT NULL — Ordre d'affichage effectif
- `rules_description` — TEXT, NULLABLE — Description des règles affichée en projection
- `started_at` — DATETIME, NULLABLE — Heure de début effective
- `ended_at` — DATETIME, NULLABLE — Heure de fin effective
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE`
- `UNIQUE (session_id, round_number)`
- `INDEX (session_id, status)`

---

### 3.5 `questions`

> Questions rattachées à une manche.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_round_id` — BIGINT UNSIGNED, NOT NULL — FK → session_rounds.id
- `text` — TEXT, NOT NULL — Énoncé de la question
- `media_type` — ENUM('none', 'image', 'video', 'audio'), NOT NULL, défaut : 'none' — Type de média associé
- `media_url` — VARCHAR(500), NULLABLE — URL du média
- `answer_type` — ENUM('qcm', 'number', 'text'), NOT NULL — Type de réponse attendue
- `correct_answer` — VARCHAR(500), NOT NULL — Réponse correcte (texte, nombre ou label du choix correct)
- `number_is_decimal` — BOOLEAN, NOT NULL, défaut : FALSE — Si answer_type=number : accepter les décimales
- `duration` — INT UNSIGNED, NOT NULL, défaut : 30 — Durée en secondes pour répondre
- `display_order` — INT UNSIGNED, NOT NULL — Ordre d'affichage dans la manche
- `status` — ENUM('pending', 'launched', 'closed', 'revealed'), NOT NULL, défaut : 'pending' — État de la question
- `launched_at` — DATETIME(3), NULLABLE — Horodatage précis du lancement (ms)
- `closed_at` — DATETIME(3), NULLABLE — Horodatage précis de la clôture (ms)
- `revealed_at` — DATETIME(3), NULLABLE — Horodatage de la révélation de la réponse
- `assigned_player_id` — BIGINT UNSIGNED, NULLABLE — FK → session_players.id (pour les manches à tour de rôle, manches 6 et 7)
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (session_round_id) REFERENCES session_rounds(id) ON DELETE CASCADE`
- `FOREIGN KEY (assigned_player_id) REFERENCES session_players(id) ON DELETE SET NULL`
- `INDEX (session_round_id, display_order)`
- `INDEX (status)`

---

### 3.6 `question_choices`

> Propositions de réponse pour les questions QCM.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `question_id` — BIGINT UNSIGNED, NOT NULL — FK → questions.id
- `label` — VARCHAR(500), NOT NULL — Texte de la proposition
- `is_correct` — BOOLEAN, NOT NULL, défaut : FALSE — Indique si c'est la bonne réponse
- `display_order` — TINYINT UNSIGNED, NOT NULL — Ordre d'affichage (identique pour tous les joueurs)
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE`
- `INDEX (question_id, display_order)`

**Règle métier :** entre 4 et 6 choix par question QCM, exactement 1 correct.

---

### 3.7 `question_hints`

> Indices configurés pour les questions de la Manche 2.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `question_id` — BIGINT UNSIGNED, NOT NULL — FK → questions.id (UNIQUE)
- `hint_type` — ENUM('remove_choices', 'reveal_letters', 'reduce_range'), NOT NULL — Type d'indice
- `time_penalty_seconds` — INT UNSIGNED, NOT NULL, défaut : 0 — Secondes retirées du temps restant quand l'indice est activé
- `removed_choice_ids` — JSON, NULLABLE — IDs des choix à retirer (pour QCM). Ex : [2, 4]
- `revealed_letters` — JSON, NULLABLE — Positions et lettres à révéler (pour texte). Ex : {"1": "A", "4": "E"}
- `range_hint_text` — VARCHAR(255), NULLABLE — Texte d'indice pour nombre. Ex : « Entre 50 et 100 »
- `range_min` — DECIMAL(15,4), NULLABLE — Borne min (nombre)
- `range_max` — DECIMAL(15,4), NULLABLE — Borne max (nombre)
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE`
- `UNIQUE (question_id)`

---

### 3.8 `second_chance_questions`

> Questions de seconde chance (rattrapage) pour la Manche 3. Associées 1:1 à une question principale.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `main_question_id` — BIGINT UNSIGNED, NOT NULL — FK → questions.id (question principale, UNIQUE)
- `text` — TEXT, NOT NULL — Énoncé de la question de rattrapage
- `media_type` — ENUM('none', 'image', 'video', 'audio'), NOT NULL, défaut : 'none' — Type de média
- `media_url` — VARCHAR(500), NULLABLE — URL du média
- `answer_type` — ENUM('qcm', 'number', 'text'), NOT NULL — Type de réponse
- `correct_answer` — VARCHAR(500), NOT NULL — Réponse correcte
- `number_is_decimal` — BOOLEAN, NOT NULL, défaut : FALSE — Accepter les décimales
- `duration` — INT UNSIGNED, NOT NULL, défaut : 30 — Durée en secondes
- `status` — ENUM('pending', 'launched', 'closed', 'revealed'), NOT NULL, défaut : 'pending' — État
- `launched_at` — DATETIME(3), NULLABLE — Horodatage du lancement
- `closed_at` — DATETIME(3), NULLABLE — Horodatage de la clôture
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (main_question_id) REFERENCES questions(id) ON DELETE CASCADE`
- `UNIQUE (main_question_id)`

---

### 3.9 `second_chance_question_choices`

> Propositions de réponse pour les questions de seconde chance de type QCM.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `second_chance_question_id` — BIGINT UNSIGNED, NOT NULL — FK → second_chance_questions.id
- `label` — VARCHAR(500), NOT NULL — Texte de la proposition
- `is_correct` — BOOLEAN, NOT NULL, défaut : FALSE — Bonne réponse ?
- `display_order` — TINYINT UNSIGNED, NOT NULL — Ordre d'affichage
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (second_chance_question_id) REFERENCES second_chance_questions(id) ON DELETE CASCADE`

---

### 3.10 `registrations`

> Inscriptions des joueurs à une session.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_id` — BIGINT UNSIGNED, NOT NULL — FK → sessions.id
- `player_id` — BIGINT UNSIGNED, NOT NULL — FK → players.id
- `status` — ENUM('registered', 'preselection_pending', 'preselection_done', 'selected', 'rejected'), NOT NULL, défaut : 'registered' — État de l'inscription
- `confirmation_email_sent_at` — DATETIME, NULLABLE — Date d'envoi de l'email de confirmation
- `selection_email_sent_at` — DATETIME, NULLABLE — Date d'envoi du lien d'accès (si sélectionné)
- `rejection_email_sent_at` — DATETIME, NULLABLE — Date d'envoi de l'email de refus
- `registered_at` — DATETIME, NOT NULL, défaut : CURRENT_TIMESTAMP — Date d'inscription
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE`
- `FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE`
- `UNIQUE (session_id, player_id)`

---

### 3.11 `preselection_questions`

> Questions du test de pré-sélection d'une session.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_id` — BIGINT UNSIGNED, NOT NULL — FK → sessions.id
- `text` — TEXT, NOT NULL — Énoncé de la question
- `media_type` — ENUM('none', 'image', 'video', 'audio'), NOT NULL, défaut : 'none' — Type de média
- `media_url` — VARCHAR(500), NULLABLE — URL du média
- `answer_type` — ENUM('qcm', 'number', 'text'), NOT NULL — Type de réponse
- `correct_answer` — VARCHAR(500), NOT NULL — Réponse correcte
- `number_is_decimal` — BOOLEAN, NOT NULL, défaut : FALSE — Accepter les décimales
- `duration` — INT UNSIGNED, NOT NULL, défaut : 30 — Durée en secondes
- `display_order` — INT UNSIGNED, NOT NULL — Ordre d'affichage
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE`
- `INDEX (session_id, display_order)`

---

### 3.12 `preselection_question_choices`

> Propositions de réponse pour les questions de pré-sélection de type QCM.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `preselection_question_id` — BIGINT UNSIGNED, NOT NULL — FK → preselection_questions.id
- `label` — VARCHAR(500), NOT NULL — Texte de la proposition
- `is_correct` — BOOLEAN, NOT NULL, défaut : FALSE — Bonne réponse ?
- `display_order` — TINYINT UNSIGNED, NOT NULL — Ordre d'affichage
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (preselection_question_id) REFERENCES preselection_questions(id) ON DELETE CASCADE`

---

### 3.13 `preselection_answers`

> Réponses des joueurs aux questions de pré-sélection.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `registration_id` — BIGINT UNSIGNED, NOT NULL — FK → registrations.id
- `preselection_question_id` — BIGINT UNSIGNED, NOT NULL — FK → preselection_questions.id
- `answer_value` — VARCHAR(500), NULLABLE — Réponse soumise par le joueur
- `selected_choice_id` — BIGINT UNSIGNED, NULLABLE — FK → preselection_question_choices.id (si QCM)
- `is_correct` — BOOLEAN, NOT NULL, défaut : FALSE — Réponse correcte ?
- `response_time_ms` — INT UNSIGNED, NULLABLE — Temps de réponse en millisecondes
- `submitted_at` — DATETIME(3), NULLABLE — Horodatage précis de soumission
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE`
- `FOREIGN KEY (preselection_question_id) REFERENCES preselection_questions(id) ON DELETE CASCADE`
- `FOREIGN KEY (selected_choice_id) REFERENCES preselection_question_choices(id) ON DELETE SET NULL`
- `UNIQUE (registration_id, preselection_question_id)`

---

### 3.14 `preselection_results`

> Résultats agrégés du test de pré-sélection par candidat.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `registration_id` — BIGINT UNSIGNED, NOT NULL — FK → registrations.id (UNIQUE)
- `correct_answers_count` — INT UNSIGNED, NOT NULL, défaut : 0 — Nombre de bonnes réponses
- `total_questions` — INT UNSIGNED, NOT NULL, défaut : 0 — Nombre total de questions
- `total_response_time_ms` — BIGINT UNSIGNED, NOT NULL, défaut : 0 — Temps total de réponse cumulé (ms)
- `rank` — INT UNSIGNED, NULLABLE — Rang dans le classement final
- `is_selected` — BOOLEAN, NOT NULL, défaut : FALSE — Joueur sélectionné pour la session ?
- `completed_at` — DATETIME, NULLABLE — Date de fin du test
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE`
- `UNIQUE (registration_id)`
- `INDEX (registration_id, rank)`

---

### 3.15 `session_players`

> Joueurs sélectionnés et participant activement à une session de jeu.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_id` — BIGINT UNSIGNED, NOT NULL — FK → sessions.id
- `player_id` — BIGINT UNSIGNED, NOT NULL — FK → players.id
- `registration_id` — BIGINT UNSIGNED, NOT NULL — FK → registrations.id
- `access_token` — VARCHAR(255), NOT NULL — Token JWT ou UUID unique d'accès à la salle de jeu
- `status` — ENUM('waiting', 'active', 'eliminated', 'finalist', 'finalist_winner', 'finalist_loser', 'abandoned'), NOT NULL, défaut : 'waiting' — Statut du joueur dans la session
- `capital` — INT, NOT NULL, défaut : 1000 — Capital actuel du joueur (initialement 1 000)
- `personal_jackpot` — INT UNSIGNED, NOT NULL, défaut : 0 — Cagnotte personnelle accumulée (manches 6+)
- `final_gain` — INT UNSIGNED, NULLABLE — Gain final du joueur à la fin de la session
- `browser_fingerprint` — VARCHAR(255), NULLABLE — Empreinte du navigateur pour anti-triche
- `is_connected` — BOOLEAN, NOT NULL, défaut : FALSE — Joueur actuellement connecté via WebSocket
- `last_connected_at` — DATETIME, NULLABLE — Dernière connexion active
- `eliminated_at` — DATETIME, NULLABLE — Date et heure d'élimination
- `elimination_reason` — VARCHAR(255), NULLABLE — Raison de l'élimination
- `eliminated_in_round_id` — BIGINT UNSIGNED, NULLABLE — FK → session_rounds.id (manche d'élimination)
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE`
- `FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE`
- `FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE`
- `FOREIGN KEY (eliminated_in_round_id) REFERENCES session_rounds(id) ON DELETE SET NULL`
- `UNIQUE (session_id, player_id)`
- `UNIQUE (access_token)`
- `INDEX (session_id, status)`

---

### 3.16 `player_answers`

> Réponses des joueurs aux questions durant le jeu.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_player_id` — BIGINT UNSIGNED, NOT NULL — FK → session_players.id
- `question_id` — BIGINT UNSIGNED, NOT NULL — FK → questions.id
- `is_second_chance` — BOOLEAN, NOT NULL, défaut : FALSE — Réponse à une question de seconde chance ?
- `second_chance_question_id` — BIGINT UNSIGNED, NULLABLE — FK → second_chance_questions.id (si seconde chance)
- `answer_value` — VARCHAR(500), NULLABLE — Réponse soumise (texte ou nombre)
- `selected_choice_id` — BIGINT UNSIGNED, NULLABLE — FK → question_choices.id (si QCM principal)
- `selected_sc_choice_id` — BIGINT UNSIGNED, NULLABLE — FK → second_chance_question_choices.id (si QCM seconde chance)
- `is_correct` — BOOLEAN, NOT NULL, défaut : FALSE — Réponse correcte ?
- `hint_used` — BOOLEAN, NOT NULL, défaut : FALSE — L'indice a-t-il été utilisé pour cette réponse ?
- `response_time_ms` — INT UNSIGNED, NULLABLE — Temps de réponse en millisecondes
- `submitted_at` — DATETIME(3), NULLABLE — Horodatage précis de soumission (ms)
- `is_timeout` — BOOLEAN, NOT NULL, défaut : FALSE — Le joueur n'a pas répondu dans le temps ?
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (session_player_id) REFERENCES session_players(id) ON DELETE CASCADE`
- `FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE`
- `FOREIGN KEY (second_chance_question_id) REFERENCES second_chance_questions(id) ON DELETE SET NULL`
- `FOREIGN KEY (selected_choice_id) REFERENCES question_choices(id) ON DELETE SET NULL`
- `FOREIGN KEY (selected_sc_choice_id) REFERENCES second_chance_question_choices(id) ON DELETE SET NULL`
- `UNIQUE (session_player_id, question_id, is_second_chance)` — un joueur ne répond qu'une fois par question (principale ou seconde chance)
- `INDEX (question_id, is_correct)`
- `INDEX (session_player_id)`

---

### 3.17 `hint_usages`

> Utilisation de l'indice par un joueur durant la Manche 2.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_player_id` — BIGINT UNSIGNED, NOT NULL — FK → session_players.id
- `session_round_id` — BIGINT UNSIGNED, NOT NULL — FK → session_rounds.id (manche 2)
- `question_id` — BIGINT UNSIGNED, NOT NULL — FK → questions.id (question sur laquelle l'indice est utilisé)
- `question_hint_id` — BIGINT UNSIGNED, NOT NULL — FK → question_hints.id
- `time_remaining_before` — INT UNSIGNED, NULLABLE — Temps restant (s) avant activation
- `time_remaining_after` — INT UNSIGNED, NULLABLE — Temps restant (s) après pénalité
- `activated_at` — DATETIME(3), NOT NULL — Horodatage précis d'activation
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création

**Contraintes :**
- `FOREIGN KEY (session_player_id) REFERENCES session_players(id) ON DELETE CASCADE`
- `FOREIGN KEY (session_round_id) REFERENCES session_rounds(id) ON DELETE CASCADE`
- `FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE`
- `FOREIGN KEY (question_hint_id) REFERENCES question_hints(id) ON DELETE CASCADE`
- `UNIQUE (session_player_id, session_round_id)` — un seul indice utilisable par manche

---

### 3.18 `round_skips`

> Passages volontaires de manche (Manche 4).

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_player_id` — BIGINT UNSIGNED, NOT NULL — FK → session_players.id
- `session_round_id` — BIGINT UNSIGNED, NOT NULL — FK → session_rounds.id (manche 4)
- `capital_lost` — INT UNSIGNED, NOT NULL, défaut : 1000 — Montant du capital perdu (transféré à la cagnotte)
- `skipped_at` — DATETIME, NOT NULL — Date et heure du passage
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création

**Contraintes :**
- `FOREIGN KEY (session_player_id) REFERENCES session_players(id) ON DELETE CASCADE`
- `FOREIGN KEY (session_round_id) REFERENCES session_rounds(id) ON DELETE CASCADE`
- `UNIQUE (session_player_id, session_round_id)` — un joueur ne peut passer qu'une seule fois par manche

---

### 3.19 `eliminations`

> Historique détaillé de chaque élimination.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_player_id` — BIGINT UNSIGNED, NOT NULL — FK → session_players.id
- `session_round_id` — BIGINT UNSIGNED, NOT NULL — FK → session_rounds.id
- `question_id` — BIGINT UNSIGNED, NULLABLE — FK → questions.id (question ayant causé l'élimination)
- `reason` — ENUM('wrong_answer', 'timeout', 'second_chance_failed', 'round_skip', 'top4_cutoff', 'duel_lost', 'finale_lost', 'manual'), NOT NULL — Raison de l'élimination
- `capital_transferred` — INT UNSIGNED, NOT NULL, défaut : 1000 — Montant transféré à la cagnotte
- `eliminated_at` — DATETIME, NOT NULL — Date et heure de l'élimination
- `is_manual` — BOOLEAN, NOT NULL, défaut : FALSE — Élimination manuelle par l'admin ?
- `admin_note` — TEXT, NULLABLE — Note de l'admin (cas litigieux)
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création

**Contraintes :**
- `FOREIGN KEY (session_player_id) REFERENCES session_players(id) ON DELETE CASCADE`
- `FOREIGN KEY (session_round_id) REFERENCES session_rounds(id) ON DELETE CASCADE`
- `FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE SET NULL`
- `INDEX (session_round_id)`

---

### 3.20 `jackpot_transactions`

> Journal de toutes les modifications de la cagnotte (traçabilité complète).

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_id` — BIGINT UNSIGNED, NOT NULL — FK → sessions.id
- `session_player_id` — BIGINT UNSIGNED, NULLABLE — FK → session_players.id (joueur concerné)
- `session_round_id` — BIGINT UNSIGNED, NULLABLE — FK → session_rounds.id
- `transaction_type` — ENUM('elimination', 'round_skip', 'round6_bonus', 'round6_departure', 'finale_win', 'finale_share', 'finale_abandon_share', 'manual_adjustment'), NOT NULL — Type de transaction
- `amount` — INT, NOT NULL — Montant (positif = ajout à la cagnotte, négatif = retrait)
- `jackpot_before` — INT UNSIGNED, NOT NULL — Cagnotte avant la transaction
- `jackpot_after` — INT UNSIGNED, NOT NULL — Cagnotte après la transaction
- `description` — VARCHAR(500), NULLABLE — Description libre
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création

**Contraintes :**
- `FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE`
- `FOREIGN KEY (session_player_id) REFERENCES session_players(id) ON DELETE SET NULL`
- `FOREIGN KEY (session_round_id) REFERENCES session_rounds(id) ON DELETE SET NULL`
- `INDEX (session_id, created_at)`

---

### 3.21 `round_rankings`

> Classement des joueurs à la fin d'une manche (utilisé surtout pour la Manche 5 et le classement intermédiaire).

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_round_id` — BIGINT UNSIGNED, NOT NULL — FK → session_rounds.id
- `session_player_id` — BIGINT UNSIGNED, NOT NULL — FK → session_players.id
- `correct_answers_count` — INT UNSIGNED, NOT NULL, défaut : 0 — Nombre de bonnes réponses dans la manche
- `total_response_time_ms` — BIGINT UNSIGNED, NOT NULL, défaut : 0 — Temps de réponse cumulé (ms)
- `rank` — INT UNSIGNED, NOT NULL — Rang dans le classement
- `is_qualified` — BOOLEAN, NOT NULL, défaut : FALSE — Qualifié pour la suite ?
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (session_round_id) REFERENCES session_rounds(id) ON DELETE CASCADE`
- `FOREIGN KEY (session_player_id) REFERENCES session_players(id) ON DELETE CASCADE`
- `UNIQUE (session_round_id, session_player_id)`
- `INDEX (session_round_id, rank)`

---

### 3.22 `finale_choices`

> Choix des finalistes en Manche 8 (Continuer ou Abandonner).

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_id` — BIGINT UNSIGNED, NOT NULL — FK → sessions.id
- `session_player_id` — BIGINT UNSIGNED, NOT NULL — FK → session_players.id
- `choice` — ENUM('continue', 'abandon'), NOT NULL — Choix du finaliste
- `chosen_at` — DATETIME(3), NOT NULL — Horodatage du choix
- `revealed` — BOOLEAN, NOT NULL, défaut : FALSE — Le choix a-t-il été révélé publiquement ?
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création

**Contraintes :**
- `FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE`
- `FOREIGN KEY (session_player_id) REFERENCES session_players(id) ON DELETE CASCADE`
- `UNIQUE (session_id, session_player_id)`

---

### 3.23 `final_results`

> Résultats finaux de la session pour chaque joueur significatif (finalistes, gagnants).

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_id` — BIGINT UNSIGNED, NOT NULL — FK → sessions.id
- `session_player_id` — BIGINT UNSIGNED, NOT NULL — FK → session_players.id
- `finale_scenario` — ENUM('both_continue_both_win', 'both_continue_one_wins', 'both_continue_both_fail', 'one_abandons', 'both_abandon'), NULLABLE — Scénario de la finale
- `final_gain` — INT UNSIGNED, NOT NULL, défaut : 0 — Gain final du joueur
- `is_winner` — BOOLEAN, NOT NULL, défaut : FALSE — Gagnant principal ?
- `position` — TINYINT UNSIGNED, NULLABLE — Position finale (1er, 2e, etc.)
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création

**Contraintes :**
- `FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE`
- `FOREIGN KEY (session_player_id) REFERENCES session_players(id) ON DELETE CASCADE`
- `UNIQUE (session_id, session_player_id)`

---

### 3.24 `round6_turn_order`

> Ordre de passage des joueurs pour les manches à tour de rôle (Manches 6 et 7).

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_round_id` — BIGINT UNSIGNED, NOT NULL — FK → session_rounds.id
- `session_player_id` — BIGINT UNSIGNED, NOT NULL — FK → session_players.id
- `turn_order` — TINYINT UNSIGNED, NOT NULL — Position dans l'ordre de passage (1, 2, 3, 4)
- `is_active` — BOOLEAN, NOT NULL, défaut : TRUE — Joueur encore actif dans la rotation
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (session_round_id) REFERENCES session_rounds(id) ON DELETE CASCADE`
- `FOREIGN KEY (session_player_id) REFERENCES session_players(id) ON DELETE CASCADE`
- `UNIQUE (session_round_id, session_player_id)`
- `UNIQUE (session_round_id, turn_order)`

---

### 3.25 `round6_player_jackpots`

> Cagnotte personnelle des joueurs durant la Manche 6 (bonus cumulés).

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_round_id` — BIGINT UNSIGNED, NOT NULL — FK → session_rounds.id
- `session_player_id` — BIGINT UNSIGNED, NOT NULL — FK → session_players.id
- `bonus_count` — INT UNSIGNED, NOT NULL, défaut : 0 — Nombre de bonnes réponses (bonus ×1 000)
- `personal_jackpot` — INT UNSIGNED, NOT NULL, défaut : 1000 — Capital initial (1 000) + bonus accumulés
- `departed_with` — INT UNSIGNED, NULLABLE — Montant emporté si éliminé (= personal_jackpot au moment de l'élimination)
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (session_round_id) REFERENCES session_rounds(id) ON DELETE CASCADE`
- `FOREIGN KEY (session_player_id) REFERENCES session_players(id) ON DELETE CASCADE`
- `UNIQUE (session_round_id, session_player_id)`

---

### 3.26 `session_events`

> Journal d'événements temps réel de la session (audit et replay).

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_id` — BIGINT UNSIGNED, NOT NULL — FK → sessions.id
- `event_type` — VARCHAR(50), NOT NULL — Type d'événement (question:launch, answer:submit, player:eliminated, etc.)
- `actor_type` — ENUM('system', 'admin', 'player'), NOT NULL — Source de l'événement
- `actor_id` — BIGINT UNSIGNED, NULLABLE — ID de l'acteur (admin_id ou session_player_id)
- `session_round_id` — BIGINT UNSIGNED, NULLABLE — FK → session_rounds.id
- `question_id` — BIGINT UNSIGNED, NULLABLE — FK → questions.id
- `payload` — JSON, NULLABLE — Données complémentaires (détails de l'événement)
- `occurred_at` — DATETIME(3), NOT NULL — Horodatage précis (ms)
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création

**Contraintes :**
- `FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE`
- `FOREIGN KEY (session_round_id) REFERENCES session_rounds(id) ON DELETE SET NULL`
- `FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE SET NULL`
- `INDEX (session_id, event_type)`
- `INDEX (session_id, occurred_at)`

---

### 3.27 `player_connections`

> Historique des connexions/déconnexions WebSocket des joueurs.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_player_id` — BIGINT UNSIGNED, NOT NULL — FK → session_players.id
- `session_id` — BIGINT UNSIGNED, NOT NULL — FK → sessions.id
- `event` — ENUM('connected', 'disconnected', 'reconnected'), NOT NULL — Type d'événement
- `ip_address` — VARCHAR(45), NULLABLE — Adresse IP du joueur
- `user_agent` — VARCHAR(500), NULLABLE — User-Agent du navigateur
- `browser_fingerprint` — VARCHAR(255), NULLABLE — Empreinte du navigateur
- `occurred_at` — DATETIME(3), NOT NULL — Horodatage précis
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création

**Contraintes :**
- `FOREIGN KEY (session_player_id) REFERENCES session_players(id) ON DELETE CASCADE`
- `FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE`
- `INDEX (session_player_id, occurred_at)`
- `INDEX (session_id, event)`

---

### 3.28 `projection_accesses`

> Accès à l'interface de projection par session.

- `id` — BIGINT UNSIGNED, NOT NULL, AUTO_INCREMENT — Clé primaire
- `session_id` — BIGINT UNSIGNED, NOT NULL — FK → sessions.id
- `access_code` — VARCHAR(10), NOT NULL — Code d'accès simple
- `is_active` — BOOLEAN, NOT NULL, défaut : TRUE — Accès actif
- `last_sync_at` — DATETIME, NULLABLE — Dernière synchronisation
- `ip_address` — VARCHAR(45), NULLABLE — IP de l'écran de projection
- `created_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de création
- `updated_at` — TIMESTAMP, NOT NULL, défaut : CURRENT_TIMESTAMP — Date de modification

**Contraintes :**
- `FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE`
- `UNIQUE (session_id, access_code)`

---

## 4. Index recommandés

En plus des index définis dans les contraintes de chaque table, les index composites suivants sont recommandés pour les performances :

```sql
-- Recherche rapide des sessions par statut et date
CREATE INDEX idx_sessions_status_scheduled ON sessions (status, scheduled_at);

-- Recherche des joueurs actifs dans une session
CREATE INDEX idx_session_players_active ON session_players (session_id, status) WHERE status = 'active';

-- Recherche des réponses par question (pour évaluation rapide)
CREATE INDEX idx_player_answers_question ON player_answers (question_id, is_correct, response_time_ms);

-- Classement pré-sélection
CREATE INDEX idx_preselection_results_rank ON preselection_results (registration_id, correct_answers_count DESC, total_response_time_ms ASC);

-- Événements de session pour replay
CREATE INDEX idx_session_events_timeline ON session_events (session_id, occurred_at);

-- Éliminations par manche
CREATE INDEX idx_eliminations_round ON eliminations (session_round_id, eliminated_at);

-- Transactions cagnotte
CREATE INDEX idx_jackpot_tx_session ON jackpot_transactions (session_id, created_at);
```

---

## 5. Contraintes et règles métier

### 5.1 Validations de la configuration des manches

| Règle | Description |
|-------|-------------|
| R1 | Les manches 5, 6, 7 et 8 doivent toujours être actives (`is_active = TRUE`) |
| R2 | Au moins une des manches 1, 2 ou 3 doit être active |
| R3 | La manche 4 est optionnelle |
| R4 | Chaque question QCM doit avoir entre 4 et 6 `question_choices` avec exactement 1 `is_correct = TRUE` |
| R5 | Chaque question de Manche 2 active doit avoir un `question_hint` associé |
| R6 | Chaque question de Manche 3 active doit avoir un `second_chance_question` associé |

### 5.2 Validations des réponses

| Règle | Description |
|-------|-------------|
| R7 | Un joueur ne peut soumettre qu'une seule réponse par question (`UNIQUE` sur `session_player_id, question_id, is_second_chance`) |
| R8 | La soumission est refusée si la question n'est pas au statut `launched` |
| R9 | La soumission est refusée si le timer serveur a expiré |
| R10 | Pour les réponses texte : comparaison insensible à la casse et aux accents (collation MySQL ou normalisation applicative) |

### 5.3 Validations des indices

| Règle | Description |
|-------|-------------|
| R11 | Un joueur ne peut utiliser qu'un seul indice par manche 2 (`UNIQUE` sur `session_player_id, session_round_id`) |
| R12 | L'indice QCM doit conserver au moins 2 propositions dont la correcte |
| R13 | Si le temps restant après pénalité ≤ 0, le joueur est considéré en échec |

### 5.4 Validations de la cagnotte

| Règle | Description |
|-------|-------------|
| R14 | `sessions.jackpot` doit toujours être égal à la somme des `jackpot_transactions.amount` pour cette session |
| R15 | Chaque élimination transfère exactement 1 000 à la cagnotte |
| R16 | Chaque passage de manche (Manche 4) transfère exactement 1 000 |

### 5.5 Validations de la Manche 5

| Règle | Description |
|-------|-------------|
| R17 | Exactement 4 joueurs doivent être qualifiés (`is_qualified = TRUE`) dans `round_rankings` |
| R18 | Le classement se fait par `correct_answers_count DESC`, puis `total_response_time_ms ASC` |

### 5.6 Validations de la Manche 6

| Règle | Description |
|-------|-------------|
| R19 | Exactement 4 joueurs dans `round6_turn_order` |
| R20 | Le joueur éliminé repart avec `personal_jackpot` (capital + bonus × 1 000) |
| R21 | Bonne réponse = bonus de +1 000 ajouté à `personal_jackpot` |

### 5.7 Validations de la Finale (Manche 8)

| Règle | Description |
|-------|-------------|
| R22 | Exactement 2 finalistes dans `finale_choices` |
| R23 | Les choix sont secrets jusqu'à révélation (`revealed = FALSE` initialement) |
| R24 | Scénario 1 (2 continuent, 2 réussissent) : cagnotte ÷ 2 |
| R25 | Scénario 2 (2 continuent, 1 échoue) : gagnant = totalité de la cagnotte |
| R26 | Scénario 3 (2 continuent, 2 échouent) : chacun repart avec 2 000 |
| R27 | Scénario 4 (1 abandonne) : abandonneur = 2 000, l'autre répond seul |
| R28 | Scénario 5 (2 abandonnent) : 5 000 chacun |

---

## 6. Notes sur les migrations Laravel

### 6.1 Ordre de création des migrations

Les migrations doivent être créées dans l'ordre suivant pour respecter les dépendances de clés étrangères :

```
01 - create_admins_table
02 - create_players_table
03 - create_sessions_table
04 - create_session_rounds_table
05 - create_questions_table
06 - create_question_choices_table
07 - create_question_hints_table
08 - create_second_chance_questions_table
09 - create_second_chance_question_choices_table
10 - create_registrations_table
11 - create_preselection_questions_table
12 - create_preselection_question_choices_table
13 - create_session_players_table
14 - create_preselection_answers_table
15 - create_preselection_results_table
16 - create_player_answers_table
17 - create_hint_usages_table
18 - create_round_skips_table
19 - create_eliminations_table
20 - create_jackpot_transactions_table
21 - create_round_rankings_table
22 - create_finale_choices_table
23 - create_final_results_table
24 - create_round6_turn_order_table
25 - create_round6_player_jackpots_table
26 - create_session_events_table
27 - create_player_connections_table
28 - create_projection_accesses_table
29 - add_current_round_and_question_fk_to_sessions (FK différées)
```

### 6.2 Conventions Laravel

| Convention | Application |
|-----------|-------------|
| Clé primaire | `id` (BIGINT UNSIGNED AUTO_INCREMENT) |
| Timestamps | `created_at` / `updated_at` via `$table->timestamps()` |
| Soft deletes | Non utilisé (les éliminations sont traçées, pas supprimées) |
| Nommage FK | `{table_singulier}_id` (ex : `session_id`, `player_id`) |
| Nommage pivot | Ordre alphabétique (ex : aucune table pivot pure ici, relations via tables dédiées) |
| Enum | Via `ENUM` MySQL ou constantes PHP dans les modèles |

### 6.3 Modèles Eloquent attendus

```
App\Models\Admin
App\Models\Player
App\Models\Session
App\Models\SessionRound
App\Models\Question
App\Models\QuestionChoice
App\Models\QuestionHint
App\Models\SecondChanceQuestion
App\Models\SecondChanceQuestionChoice
App\Models\Registration
App\Models\PreselectionQuestion
App\Models\PreselectionQuestionChoice
App\Models\PreselectionAnswer
App\Models\PreselectionResult
App\Models\SessionPlayer
App\Models\PlayerAnswer
App\Models\HintUsage
App\Models\RoundSkip
App\Models\Elimination
App\Models\JackpotTransaction
App\Models\RoundRanking
App\Models\FinaleChoice
App\Models\FinalResult
App\Models\Round6TurnOrder
App\Models\Round6PlayerJackpot
App\Models\SessionEvent
App\Models\PlayerConnection
App\Models\ProjectionAccess
```

---

## Récapitulatif des tables

| # | Table | Description | Nb estimé de lignes par session |
|---|-------|-------------|-------------------------------|
| 1 | `admins` | Administrateurs | Quelques unités (global) |
| 2 | `players` | Joueurs | Centaines à milliers (global) |
| 3 | `sessions` | Sessions de jeu | Dizaines (global) |
| 4 | `session_rounds` | Manches par session | 5 à 8 |
| 5 | `questions` | Questions de jeu | 20 à 80 |
| 6 | `question_choices` | Choix QCM | 80 à 480 |
| 7 | `question_hints` | Indices (Manche 2) | 5 à 15 |
| 8 | `second_chance_questions` | Questions seconde chance (M3) | 5 à 15 |
| 9 | `second_chance_question_choices` | Choix QCM seconde chance | 20 à 90 |
| 10 | `registrations` | Inscriptions | Centaines à milliers |
| 11 | `preselection_questions` | Questions pré-sélection | 10 à 30 |
| 12 | `preselection_question_choices` | Choix QCM pré-sélection | 40 à 180 |
| 13 | `preselection_answers` | Réponses pré-sélection | Milliers |
| 14 | `preselection_results` | Résultats pré-sélection | Centaines à milliers |
| 15 | `session_players` | Joueurs sélectionnés | Jusqu'à 500 |
| 16 | `player_answers` | Réponses en jeu | Milliers |
| 17 | `hint_usages` | Utilisations d'indices | Jusqu'à 500 |
| 18 | `round_skips` | Passages de manche | Dizaines |
| 19 | `eliminations` | Éliminations | Jusqu'à ~496 |
| 20 | `jackpot_transactions` | Transactions cagnotte | Centaines |
| 21 | `round_rankings` | Classements par manche | Centaines |
| 22 | `finale_choices` | Choix des finalistes | 2 |
| 23 | `final_results` | Résultats finaux | 2 à 4 |
| 24 | `round6_turn_order` | Ordre de passage M6/M7 | 2 à 4 |
| 25 | `round6_player_jackpots` | Cagnottes perso M6 | 4 |
| 26 | `session_events` | Événements temps réel | Milliers |
| 27 | `player_connections` | Connexions WebSocket | Centaines à milliers |
| 28 | `projection_accesses` | Accès projection | 1 à 3 |

**Total : 28 tables**
