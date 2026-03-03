La suite — ce qu'il reste à faire
Priorité	Tâche	Détail
P0	Corriger les 7 bugs critiques	Enum AnswerType, Resources, Form Request, HintUsage columnes
P1	Logique manche 3 (Seconde Chance)	Le controller Admin/GameController ne gère pas la seconde chance (round type SecondChance) — pas de logique pour lancer la question SC après une mauvaise réponse
P1	Logique manches 5-8	Top4Elimination, DuelJackpot, DuelElimination, Finale — aucune logique spécifique n'est implémentée dans les controllers
P2	CRUD Preselection Questions admin	Pas de controller admin pour créer les questions de pré-sélection
P2	Factories / Seeders complets	Seul le seeder de base existe (1 admin, 1 session, 8 rounds). Pas de factory pour Question, Registration, etc.
P2	Tests Pest	Aucun test métier écrit
P3	Projection publique	Controller pour la vue projection en temps réel
P3	Notifications (emails)	Confirmation d'inscription, sélection, rejet
P3	Événements / Broadcasting	Pour le temps réel (WebSocket)
