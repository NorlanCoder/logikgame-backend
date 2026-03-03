 

**CAHIER DES CHARGES**

**LOGIK GAME**

 

*Application de jeu de logique interactive en temps réel*

Inspirée de l’émission télévisée « 100% Logique »

**TABLE DES MATIÈRES**

 

**1\.**  Introduction et contexte

**2\.**  Glossaire et définitions

**3\.**  Acteurs et rôles

**4\.**  Architecture fonctionnelle

**5\.**  Module Administration — Création de session

**6\.**  Module Questions — Structure et types

**7\.**  Système d’indices (Manche 2\)

**8\.**  Questions de seconde chance (Manche 3\)

**9\.**  Pré-sélection et inscriptions

**10\.**  Déroulement général du jeu

**11\.**  Détail des 8 manches

**12\.**  Système de cagnotte et scoring

**13\.**  Gestion du temps réel

**14\.**  Les 3 interfaces utilisateur

**15\.**  Exigences techniques

**16\.**  Exigences non fonctionnelles

**17\.**  Sécurité et conformité

**18\.**  Livrables et jalons

# **1\. Introduction et contexte**

## **1.1 Présentation du projet**

LOGIK GAME est une application web et mobile interactive qui reproduit la mécanique de l’émission télévisée « 100% Logique ». Elle permet à un administrateur de créer et piloter des sessions de jeu en temps réel, dans lesquelles des joueurs s’affrontent sur 8 manches éliminatoires pour remporter une cagnotte cumulative.

L’application couvre l’intégralité du cycle de vie d’une session : inscription des joueurs, pré-sélection, déroulement des manches avec gestion du temps réel, éliminations progressives, et répartition finale de la cagnotte.

## **1.2 Objectifs**

•        Offrir une expérience de jeu immersive et fluide en temps réel.

•        Permettre à l’administrateur un contrôle total sur le déroulement du jeu.

•        Gérer de manière automatique les éliminations, les scores et la cagnotte.

•        Garantir l’équité entre les joueurs (synchronisation, anti-triche).

•        Offrir une interface intuitive et responsive (web, mobile, tablette).

## **1.3 Périmètre**

Le périmètre du projet couvre les modules suivants : administration (création de sessions, gestion des manches et questions), inscriptions et pré-sélection, salle de jeu en temps réel, système de scoring et cagnotte, et tableau de bord de résultats.

# **2\. Glossaire et définitions**

| Terme | Définition |
| ----- | ----- |
| Session | Instance complète d’un jeu LOGIK GAME, de l’inscription à la finale. |
| Manche | Phase distincte du jeu avec ses propres règles. 8 manches au total. |
| Cagnotte | Montant cumulé alimentaire par les éliminations des joueurs (1 000 par joueur éliminé). |
| Mort subite | Règle où toute mauvaise réponse entraîne l’élimination immédiate. |
| Indice | Aide fournie au joueur (retrait de choix, lettres révélées, etc.) utilisable une seule fois par manche. |
| Seconde chance | Mécanique permettant aux joueurs ayant échoué de tenter une question de rattrapage. |
| Pré-sélection | Test d’entrée pour filtrer les joueurs avant le jeu principal. |
| Lien unique | URL personnalisée et sécurisée envoyée aux joueurs sélectionnés pour accéder à la salle de jeu. |
| Passer sa manche | Action volontaire d’un joueur de quitter la manche en cours, entraînant la perte de son capital de 1 000. |

# **3\. Acteurs et rôles**

## **3.1 Administrateur (Game Master)**

L’administrateur est le maître du jeu. Il dispose des droits suivants :

•        Créer, configurer et supprimer des sessions de jeu.

•        Définir les manches actives (obligatoires : au moins une des 3 premières \+ toutes à partir de la manche 5).

•        Ajouter, modifier et supprimer les questions pour chaque manche.

•        Configurer les indices, les questions de seconde chance et de pré-sélection.

•        Lancer les inscriptions et clôturer la phase de pré-sélection.

•        Lancer chaque question individuellement en temps réel.

•        Visualiser le tableau de bord en direct (réponses, scores, éliminations).

•        Gérer manuellement les cas litigieux (éliminations manuelles, rectifications).

## **3.2 Joueur**

Le joueur est le participant au jeu. Il dispose des capacités suivantes :

•        S’inscrire à une session de jeu ouverte.

•        Passer le test de pré-sélection.

•        Accéder à la salle de jeu via un lien unique (si sélectionné).

•        Répondre aux questions dans le temps imparti (réponse unique, non modifiable).

•        Utiliser un indice (une seule fois par manche, si disponible).

•        Passer sa manche (avec pénalité de 1 000).

•        Consulter son score et son statut en temps réel.

## **3.3 Écran de projection (troisième interface)**

L’écran de projection est une interface dédiée destinée à être affichée sur un écran externe (vidéoprojecteur, télévision, écran géant). Cette interface est en mode lecture seule et n’offre aucune possibilité d’interaction. Elle affiche :

•        La manche en cours (numéro, nom, règles).

•        La question en cours (texte et média associé) dès que l’administrateur la lance.

•        Le décompte du timer en temps réel.

•        La bonne réponse après la clôture.

•        La liste des joueurs éliminés après chaque question.

•        Le montant de la cagnotte en temps réel.

•        Les transitions entre manches (animation, récapitulatif).

*Cette interface ne montre jamais les réponses des joueurs individuels avant la clôture de la question. Elle est synchronisée avec le serveur via WebSocket et pilotée par les actions de l’administrateur.*

## **3.4 Rôle central de l’administrateur pendant le jeu**

L’administrateur est le pilote exclusif du déroulement du jeu en temps réel. C’est lui qui contrôle chaque étape :

•        Il décide quand lancer chaque question (les joueurs attendent son signal).

•        Il décide quand passer à la question suivante ou à la manche suivante.

•        Il déclenche l’affichage de la bonne réponse et des éliminés.

•        Il contrôle le rythme du jeu (pause entre les questions, transitions).

•        Aucune question ne peut être affichée sur les interfaces joueur ou projection tant que l’administrateur ne l’a pas explicitement lancée.

# **4\. Architecture fonctionnelle**

L’application se décompose en six modules principaux, reposant sur trois interfaces distinctes :

| Module | Description | Accès |
| ----- | ----- | ----- |
| Administration | Création de sessions, manches, questions, pilotage du jeu en direct | Administrateur |
| Inscription & Pré-sélection | Formulaire d’inscription, test de pré-sélection, classement | Public / Joueurs |
| Salle de jeu | Interface temps réel pour répondre aux questions, affichage des manches | Joueurs sélectionnés |
| Écran de projection | Affichage public des questions, manches, timer, éliminés et cagnotte | Projection (lecture seule) |
| Moteur de jeu | Logique métier : scoring, éliminations, cagnotte, transitions de manches | Système (back-end) |
| Tableau de bord | Visualisation en direct des résultats, classements, statistiques | Administrateur |

# **5\. Module Administration — Création de session**

## **5.1 Création d’une session**

L’administrateur crée une nouvelle session en renseignant les informations suivantes :

| Champ | Type | Obligatoire | Description |
| ----- | ----- | ----- | ----- |
| Nom de la session | Texte | Oui | Identifiant lisible de la session (ex. : « LOGIK S01E03 ») |
| Date et heure | Datetime | Oui | Date et heure prévues du lancement |
| Nombre max de joueurs | Nombre | Oui | Nombre maximum de joueurs sélectionnés après pré-sélection |
| Description | Texte long | Non | Description publique de la session |
| Image de couverture | Fichier image | Non | Visuel promotionnel de la session |

## **5.2 Configuration des manches**

Une session comporte 8 manches au total. L’administrateur doit sélectionner les manches actives selon les règles suivantes :

•        **Manches 5 à 8 :** obligatoires et toujours actives.

•        **Manches 1 à 3 :** au moins une doit être sélectionnée (l’administrateur choisit laquelle ou lesquelles).

•        **Manche 4 :** optionnelle.

 

*Le système empêche la validation de la configuration si ces règles ne sont pas respectées.*

## **5.3 Récapitulatif des manches**

| N° | Nom de la manche | Mécanique principale | Obligatoire |
| ----- | ----- | ----- | ----- |
| 1 | Mort subite | Mauvaise réponse \= élimination immédiate | Au moins 1 des 3 |
| 2 | Utilisation d’indice | Un indice utilisable une fois pendant la manche | Au moins 1 des 3 |
| 3 | Seconde chance | Les éliminés ont une question de rattrapage | Au moins 1 des 3 |
| 4 | Passage de manche | Un joueur peut passer mais perd ses 1 000 | Optionnelle |
| 5 | Élimination pour les 4 finalistes | Sélection des 4 meilleurs (vitesse \+ justesse) | Obligatoire |
| 6 | Duel — Tour de rôle (cagnotte) | 1 vs 1, le perdant repart avec sa cagnotte | Obligatoire |
| 7 | Duel — Élimination | 1 vs 1, le perdant est éliminé → 2 finalistes | Obligatoire |
| 8 | Finale | Les 2 finalistes choisissent de continuer ou non | Obligatoire |

# **6\. Module Questions — Structure et types**

## **6.1 Structure d’une question**

Chaque question est constituée des éléments suivants :

| Champ | Type | Obligatoire | Description |
| ----- | ----- | ----- | ----- |
| Texte de la question | Texte | Oui | L’énoncé de la question affiché au joueur |
| Média | Image / Vidéo / Audio | Non | Support visuel ou audio accompagnant la question |
| Type de réponse | Enum | Oui | QCM, Nombre, ou Texte libre |
| Réponse correcte | Variable | Oui | La réponse acceptée comme correcte |
| Durée | Nombre (secondes) | Oui | Temps accordé pour répondre (défaut : 30 secondes) |
| Ordre d’affichage | Nombre | Oui | Position de la question dans la manche |

## **6.2 Types de réponses**

**6.2.1 Question à Choix Multiples (QCM)**

•        L’administrateur définit entre 4 et 6 propositions de réponse.

•        Une seule correcte.

•        L’ordre des propositions est la même pour tous les joueurs.

•        Le joueur sélectionne sa réponse en un clic. La réponse est définitive.

**6.2.2 Réponse numérique**

•        Le joueur saisit un nombre (entier ou décimal, selon configuration).

•        Validation en temps réel du format de saisie.

**6.2.3 Réponse texte libre**

•        Le joueur saisit un texte.

•        Comparaison insensible à la casse et aux accents.

## **6.3 Règles de soumission**

•        **Réponse unique :** le joueur ne peut soumettre qu’une seule réponse par question. Toute soumission est définitive et non modifiable.

•        **Lancement par l’admin :** le joueur ne voit la question et ne peut répondre qu’après que l’administrateur a lancé la question.

•        **Timeout :** si le joueur ne répond pas dans le temps imparti, sa réponse est considérée comme fausse.

•        **Horodatage :** le temps de réponse est enregistré avec précision (millisecondes) pour les départages.

# **7\. Système d’indices (Manche 2\)**

## **7.1 Principe général**

Lors de la Manche 2, chaque joueur dispose d’un indice utilisable une seule fois au cours de toute la manche. L’activation de l’indice est irréversible. L’indice est spécifique au type de question.

## **7.2 Configuration des indices par l’administrateur**

Pour chaque question de la Manche 2, l’administrateur configure les éléments d’indice suivants :

| Type de question | Nature de l’indice | Configuration requise |
| ----- | ----- | ----- |
| QCM | Retrait de propositions incorrectes | L’admin sélectionne les choix à retirer (1 à N-2 choix, en conservant toujours au moins 2 options dont la bonne) |
| Texte libre | Affichage de lettres | L’admin définit les positions et lettres à révéler (ex. : \_ A \_ \_ E pour « TABLE ») |
| Nombre | Réduction de l’intervalle | L’admin définit un indice textuel (ex. : « Entre 50 et 100 ») ou une borne min/max |

## **7.3 Réduction du temps**

En plus de l’indice spécifique au type de question, l’administrateur peut configurer un nombre de secondes à retirer du temps restant lorsque l’indice est activé. Cette pénalité temporelle est configurable par question (ex. : \-5 secondes, \-10 secondes). Si le temps restant après la pénalité est inférieur ou égal à 0, le joueur est considéré comme ayant échoué la question.

## **7.4 Interface joueur**

•        Un bouton « Utiliser mon indice » est visible pendant toute la manche 2\.

•        Le bouton affiche un indicateur de disponibilité (« Disponible » / « Utilisé »).

•        Après utilisation, le bouton est grisé pour le reste de la manche.

# **8\. Questions de seconde chance (Manche 3\)**

## **8.1 Principe**

Lors de la Manche 3, les joueurs ayant répondu incorrectement à une question ne sont pas immédiatement éliminés. Ils entrent dans une phase de « seconde chance » où ils doivent répondre à une question de rattrapage.

## **8.2 Déroulement**

•        **Étape 1 — Question principale :** tous les joueurs répondent à la question principale.

•        **Étape 2 — Séparation :** ceux qui ont répondu correctement attendent. Ceux qui ont échoué passent en phase de seconde chance.

•        **Étape 3 — Question de seconde chance :** une question de rattrapage est posée uniquement aux joueurs en échec.

•        **Étape 4 — Résultat :** ceux qui réussissent la question de seconde chance sont réintégrés. Ceux qui échouent sont éliminés définitivement.

## **8.3 Configuration**

L’administrateur associe une question de seconde chance à chaque question principale de la Manche 3\. La question de seconde chance suit la même structure (texte, média, type de réponse, durée)..

## **8.4 Interface**

•        Les joueurs en attente voient un écran « En attente de la seconde chance... » avec un indicateur de progression.

•        Les joueurs en seconde chance voient la question de rattrapage avec un indicateur visuel spécifique (ex. : bordure orange, icône d’alerte).

•        L’administrateur voit les deux groupes en temps réel sur son tableau de bord.

# **9\. Pré-sélection et inscriptions**

## **9.1 Phase d’inscription**

L’administrateur ouvre les inscriptions pour une session. Le formulaire d’inscription collecte les informations suivantes :

| Champ | Type | Obligatoire |
| ----- | ----- | ----- |
| Nom complet | Texte | Oui |
| Adresse e-mail | Email | Oui |
| Numéro de téléphone | Téléphone | Oui |
| Pseudo (nom de jeu) | Texte | Oui |
| Photo de profil | Image | Non |

L’inscription est ouverte pendant une durée configurable par l’administrateur. Un e-mail de confirmation est envoyé à chaque inscrit.

## **9.2 Test de pré-sélection**

Les inscrits passent un test de pré-sélection composé de questions définies par l’administrateur. Le test est accessible une seule fois par inscrit, pendant une fenêtre de temps définie.

•        Les questions de pré-sélection suivent la même structure que les questions du jeu (texte, média, type de réponse, durée).

•        Le score est calculé sur la base du nombre de bonnes réponses et du temps total de réponse.

•        L’ordre des questions peut être aléatoire pour éviter la triche.

## **9.3 Classement et sélection**

Après clôture du test, les inscrits sont classés par rang selon les critères suivants (par ordre de priorité) :

•        **Nombre de bonnes réponses** (décroissant).

•        **Temps total de réponse** (croissant — le plus rapide en premier).

L’administrateur sélectionne les N premiers joueurs (N \= nombre max de joueurs défini dans la session). Les joueurs sélectionnés reçoivent un lien unique et sécurisé (token JWT ou UUID) par e-mail pour accéder à la salle de jeu. Les joueurs non sélectionnés reçoivent un e-mail de notification.

# **10\. Déroulement général du jeu**

## **10.1 Capital initial**

Chaque joueur démarre la session avec un capital de 1 000 points. Ce capital est lié à sa participation dans le jeu. Lorsqu’un joueur est éliminé, ses 1 000 points sont transférés dans la cagnotte collective.

## **10.2 Cycle d’une question (piloté par l’administrateur)**

L’intégralité du déroulement est pilotée par l’administrateur. Aucune transition automatique ne se produit sans son action. Le cycle de chaque question suit le processus suivant :

•        **1\. Attente :** Les joueurs et l’écran de projection affichent un écran d’attente. L’administrateur voit la question à lancer sur son interface de pilotage.

•        **2\. Lancement par l’admin :** L’administrateur clique sur « Lancer la question ». La question est immédiatement affichée sur les 3 interfaces (admin, joueurs, projection) et le timer démarre.

•        **3\. Réponse :** Les joueurs disposent du temps imparti pour soumettre une réponse unique. L’écran de projection affiche le décompte en temps réel.

•        **4\. Clôture :** Le timer expire ou tous les joueurs ont répondu. Les réponses sont évaluées côté serveur.

•        **5\. Affichage de la réponse :** L’administrateur déclenche l’affichage de la bonne réponse. Celle-ci apparaît sur l’écran de projection et sur l’interface joueur.

•        **6\. Affichage des éliminés :** Après chaque question, la liste des joueurs éliminés est affichée sur les 3 interfaces. L’écran de projection met en évidence les pseudos des éliminés, le nombre de joueurs restants et la mise à jour de la cagnotte.

•        **7\. Transition :** L’administrateur décide quand passer à la question suivante ou à la manche suivante. Il contrôle le rythme intégralement.

## **10.3 Règle de soumission**

Chaque joueur ne peut soumettre qu’une seule réponse par question. Aucune modification n’est possible après soumission. Le serveur enregistre l’horodatage exact de la soumission pour les départages ultérieurs.

# **11\. Détail des 8 manches**

## **11.1 Manche 1 — Mort subite**

**Règle**

Tout joueur qui répond incorrectement à une question est immédiatement et définitivement éliminé. Ses 1 000 points sont transférés à la cagnotte.

**Déroulement**

•        L’administrateur lance les questions une par une.

•        Après chaque question, les joueurs ayant répondu faux sont éliminés.

•        Les joueurs n’ayant pas répondu dans le temps sont également éliminés.

•        La manche se termine quand toutes les questions ont été posées.

**Interface**

•        Le joueur éliminé voit un écran de notification d’élimination avec le message : « Vous avez été éliminé. Votre capital de 1 000 rejoint la cagnotte. »

•        L’écran de projection affiche la liste des éliminés après chaque question (pseudos, nombre, mise à jour de la cagnotte).

•        L’administrateur voit en temps réel le nombre de joueurs restants et les éliminations, et contrôle le passage à la question suivante.

## **11.2 Manche 2 — Utilisation d’indice**

**Règle**

Cette manche fonctionne comme la Mort subite, avec une mécanique supplémentaire : chaque joueur peut utiliser un indice une seule fois pendant toute la manche. L’indice est configuré par l’administrateur pour chaque question (voir section 7).

**Déroulement**

•        Les questions sont lancées une par une par l’administrateur.

•        Avant de répondre, le joueur peut activer son indice (s’il ne l’a pas encore utilisé).

•        L’activation de l’indice modifie la question (retrait de choix, lettres révélées, borne) et peut réduire le temps restant.

•        Toute mauvaise réponse (avec ou sans indice) entraîne l’élimination.

## **11.3 Manche 3 — Seconde chance**

**Règle**

Les joueurs qui répondent mal à une question principale ne sont pas immédiatement éliminés. Ils passent en phase de seconde chance (voir section 8).

**Déroulement**

•        L’administrateur lance la question principale.

•        Les joueurs répondent. Deux groupes se forment : les « réussis » et les « en échec ».

•        L’administrateur lance la question de seconde chance pour le groupe en échec.

•        Les joueurs qui échouent la seconde chance sont éliminés définitivement.

•        Les joueurs qui réussissent la seconde chance sont réintégrés et continuent.

## **11.4 Manche 4 — Passage de manche**

**Règle**

Cette manche offre la possibilité aux joueurs de « passer leur manche », c’est-à-dire de quitter volontairement la manche en cours. Le joueur qui passe perd son capital de 1 000 points (transféré à la cagnotte). Il est éliminé de la manche mais reste dans le jeu pour les manches suivantes.

**Déroulement**

•        Les questions sont lancées normalement.

•        Avant chaque question (ou pendant le temps de réponse), le joueur peut cliquer sur « Passer ma manche ».

•        Le joueur qui passe est retiré de la manche en cours mais conserve sa place pour la suite.

•        Les joueurs qui répondent mal sont éliminés définitivement (comme en mort subite).

**Stratégie**

Cette mécanique crée un dilemme stratégique : le joueur doit évaluer le risque de répondre (avec possibilité d’élimination définitive) face au coût de passer (perte de 1 000 mais survie assurée).

## **11.5 Manche 5 — Élimination pour les 4 finalistes**

**Règle**

Cette manche vise à réduire le nombre de joueurs à exactement 4\. La sélection se fait sur la base de deux critères combinés : justesse des réponses et rapidité.

**Déroulement**

•        L’administrateur lance les questions de la manche.

•        Tous les joueurs répondent.

•        À la fin de la manche, le système classe les joueurs selon : (1) nombre de bonnes réponses (décroissant), puis (2) temps cumulé de réponse (croissant).

•        Les 4 premiers du classement sont sélectionnés comme finalistes.

•        Tous les autres joueurs sont éliminés. Leurs 1 000 points respectifs rejoignent la cagnotte.

**Cas d’égalité**

En cas d’égalité parfaite au 4ème rang, le joueur ayant le temps de réponse cumulé le plus court est retenu. Si l’égalité persiste, l’administrateur peut départager manuellement ou une question de départage supplémentaire est lancée.

## **11.6 Manche 6 — Question à tour de rôle (cagnotte)**

**Règle**

Les 4 finalistes s'affrontent en questions posées à tour de rôle. Chaque joueur répond individuellement à sa question pendant que les autres attendent. Une bonne réponse rapporte un bonus de \+1 000 points ajouté à la cagnotte personnelle du joueur. Une mauvaise réponse entraîne l'élimination immédiate : le joueur quitte le jeu en repartant avec la cagnotte qu'il a accumulée jusqu'à ce point.

**Déroulement**

•        Ordre de passage : L'administrateur définit l'ordre de passage des 4 joueurs (aléatoire ou basé sur le classement de la Manche 5). Cet ordre est affiché sur l'écran de projection.

•        Tour par tour : L'administrateur lance une question destinée au joueur dont c'est le tour. Seul ce joueur peut répondre. Les 3 autres joueurs et l'écran de projection voient la question mais ne peuvent pas interagir.

•        Bonne réponse : Si le joueur répond correctement, il reçoit un bonus de \+1 000 points ajouté à sa cagnotte personnelle. Son tour est terminé et on passe au joueur suivant dans l'ordre.

•        Mauvaise réponse ou timeout : Si le joueur répond incorrectement ou ne répond pas dans le temps imparti, il est immédiatement éliminé. Il quitte le jeu en repartant avec l'intégralité de sa cagnotte personnelle accumulée (capital initial de 1 000 \+ les bonus de \+1 000 gagnés à chaque bonne réponse durant cette manche). Ce montant constitue son gain final.

•        Rotation continue : Après chaque tour, on passe au joueur suivant dans l'ordre. Les joueurs éliminés sont sautés. La rotation continue en boucle.

**Cagnotte du perdant**

Le perdant de la manche 6 repart avec la cagnotte qu’il a accumulée au cours du jeu. Ce montant constitue son gain final. Le gagnant, quant à lui, voit son capital augmenté de 1 000 supplémentaires (soit 2 000 au total : capital initial \+ bonus).

## **11.7 Manche 7 — Question à tour de rôle**

**Règle**

Les gagnants de la manche 6 s’affrontent dans un dernier tour. Le perdant est éliminé et on obtient les finalistes.

**Déroulement**

•        Les questions sont posées à tour de rôle.

•        Le premier joueur à répondre incorrectement est éliminé.

•        Le gagnant et le perdant deviennent les deux finalistes de la manche 8\.

*Note : les joueurs gagnants accèdent à la finale.* 

## **11.8 Manche 8 — Finale**

**Règle**

Les finalistes font face à un choix stratégique crucial. Chaque finaliste choisit de continuer ou d’abandonner.

**Scénarios possibles**

| Scénario | Condition | Résultat |
| ----- | ----- | ----- |
| 1 — Les deux continuent et réussissent | Les 2 finalistes choisissent de continuer et répondent correctement à la dernière question | Ils se partagent la cagnotte totale (à parts égales) |
| 2 — Les deux continuent, un échoue | Les 2 continuent mais l’un échoue | Le gagnant remporte la totalité de la cagnotte |
| 3 — Les deux continuent, les deux échouent | Les 2 continuent et les 2 échouent | Chacun repart avec 2 000 (cagnotte étape 6). La cagnotte restante n’est pas distribuée. |
| 4 — Un abandonne | Un finaliste choisit d’abandonner | L’abandonneur repart avec sa cagnotte de l’étape 6 (soit 2 000). L’autre doit répondre seul. |
| 5 — Les deux abandonnent | Les 2 choisissent d’abandonner | Ils se partagent 10 000 points (à parts égales)  |

**Déroulement détaillé**

•        **Étape 1 — Choix :** Chaque finaliste voit un écran de décision : « Continuer » ou « Abandonner ». Les choix sont simultanés et secrets.

•        **Étape 2 — Révélation :** Les choix sont révélés. Si au moins un joueur continue, la question finale est posée.

•        **Étape 3 — Question finale :** La dernière question est posée aux joueurs ayant choisi de continuer.

•        **Étape 4 — Résultat final :** Le système calcule et affiche la répartition des gains selon les scénarios décrits ci-dessus.

# **12\. Système de cagnotte et scoring**

## **12.1 Capital initial**

Chaque joueur entre dans le jeu avec un capital de 1 000 points. Ce capital représente sa « mise » dans le jeu.

## **12.2 Alimentation de la cagnotte**

La cagnotte est alimentée par les éliminations :

•        À chaque élimination d’un joueur, ses 1 000 points sont ajoutés à la cagnotte.

•        Passer sa manche (Manche 4\) : les 1 000 du joueur vont dans la cagnotte, mais le joueur n’est pas éliminé du jeu.

## **12.3 Calcul de la cagnotte**

La formule de la cagnotte est la suivante :

**Cagnotte \= Nombre de joueurs éliminés × 1 000 \+ Nombre de passages (Manche 4\) × 1 000**

## **12.4 Gains aux différentes étapes**

| Étape | Gagnant | Perdant |
| ----- | ----- | ----- |
| Manches 1-5 | Continue le jeu | Éliminé — 1 000 rejoint la cagnotte |
| Manche 6  | Reçoit \+1 000 bonus | Repart avec sa cagnotte personnelle |
| Manche 7  | Finaliste n°1 | Finaliste n°2 (accède aussi à la finale) |
| Manche 8 — Double échec | — | Repart avec 2 000 (capital \+ bonus manche 6\) |
| Manche 8 — Victoire partagée | Cagnotte / 2 chacun | — |
| Manche 8 — Abandon  | 5 000 chacun | — |
| Manche 8 — Un seul gagne | Totalité de la cagnotte | 0 (ou 2 000 si abandon) |

# **13\. Gestion du temps réel**

## **13.1 Technologie**

La communication en temps réel entre le serveur et les clients (joueurs, administrateur, spectateurs) repose sur une connexion WebSocket persistante. Cette technologie garantit la diffusion instantanée des événements de jeu.

## **13.2 Événements temps réel**

| Événement | Émetteur | Récepteurs | Description |
| ----- | ----- | ----- | ----- |
| question:launch | Admin | Joueurs \+ Projection | La question est affichée et le timer démarre sur les 3 interfaces |
| answer:submit | Joueur | Serveur | Le joueur soumet sa réponse |
| answer:result | Serveur | Joueur concerné | Résultat individuel (correct/incorrect) |
| answer:reveal | Admin | Joueurs \+ Projection | L’admin déclenche l’affichage de la bonne réponse |
| eliminated:show | Serveur | Tous (3 interfaces) | Affichage de la liste des joueurs éliminés après chaque question |
| player:eliminated | Serveur | Tous (3 interfaces) | Un joueur est éliminé, mise à jour du compteur |
| timer:tick | Serveur | Joueurs \+ Projection | Mise à jour du décompte |
| timer:expired | Serveur | Joueurs \+ Projection | Temps écoulé pour la question |
| round:start | Admin | Joueurs \+ Projection | L’admin lance une nouvelle manche (affichage du nom et des règles) |
| round:end | Serveur | Tous (3 interfaces) | Fin de la manche, récapitulatif affiché |
| question:next | Admin | Joueurs \+ Projection | L’admin décide de passer à la question suivante |
| hint:activate | Joueur | Serveur | Le joueur active son indice |
| hint:applied | Serveur | Joueur concerné | L’indice est appliqué à la question |
| game:end | Serveur | Tous (3 interfaces) | Fin du jeu, répartition des gains |
| jackpot:update | Serveur | Tous (3 interfaces) | Mise à jour du montant de la cagnotte |
| projection:sync | Serveur | Projection | Synchronisation de l’état complet vers l’écran de projection |

## **13.3 Synchronisation**

Le timer est géré côté serveur pour garantir l’équité entre les joueurs. Le client affiche un timer local synchronisé avec le serveur via des pulsations régulières. La latence réseau est compensée par un mécanisme de correction (NTP simplifié).

## **13.4 Gestion de la déconnexion**

•        Si un joueur se déconnecte pendant une question, il dispose d’un délai de grâce (configurable, ex. : 10 secondes) pour se reconnecter.

•        Si le joueur ne se reconnecte pas dans le délai, sa réponse est considérée comme absente (traitée comme une mauvaise réponse selon les règles de la manche).

•        L’état du joueur est préservé côté serveur pour permettre la reconnexion.

# **14\. Les 3 interfaces utilisateur**

L’application repose sur trois interfaces distinctes, chacune ayant un rôle précis et un public dédié. Les trois interfaces sont synchronisées en temps réel via WebSocket et sont toutes pilotées par les actions de l’administrateur.

## **14.1 Interface Administrateur (Pilotage)**

**Objectif**

Permettre à l’administrateur de piloter intégralement le déroulement du jeu. C’est le poste de commande central.

**Fonctionnalités**

•        Vue d’ensemble de la session : manche en cours, nombre de joueurs restants, cagnotte.

•        Bouton « Lancer la question » pour déclencher l’affichage de chaque question sur les interfaces joueurs et projection.

•        Bouton « Afficher la réponse » pour révéler la bonne réponse après clôture.

•        Bouton « Afficher les éliminés » pour projeter la liste des éliminés après chaque question.

•        Bouton « Question suivante » / « Manche suivante » pour contrôler le rythme.

•        Tableau de bord en direct : nombre de réponses reçues, répartition correct/incorrect, temps moyens de réponse.

•        Liste des joueurs avec leur statut (actif, éliminé, en attente, en seconde chance).

•        Actions manuelles : éliminer un joueur, rectifier un score, mettre en pause.

**Contraintes**

L’interface admin ne doit jamais être visible par les joueurs ou sur l’écran de projection. Elle affiche des informations privilégiées (réponses attendues, statistiques en temps réel).

## **14.2 Interface Joueur**

**Objectif**

Permettre aux joueurs sélectionnés de participer au jeu en répondant aux questions dans le temps imparti.

**Fonctionnalités**

•        Écran d’attente affiché entre les questions (avec indication de la manche en cours).

•        Affichage de la question (texte \+ média) dès que l’administrateur la lance.

•        Zone de réponse adaptée au type de question (boutons QCM, champ numérique, champ texte).

•        Timer visible avec décompte en temps réel.

•        Bouton d’indice (Manche 2\) avec indicateur de disponibilité.

•        Bouton « Passer ma manche » (Manche 4).

•        Affichage du résultat individuel après chaque question (correct/incorrect).

•        Notification d’élimination avec détail (raison, montant transféré à la cagnotte).

•        Score personnel et cagnotte affichés en permanence.

**Contraintes**

Le joueur ne peut pas voir la question avant que l’administrateur ne l’ait lancée. La réponse est soumise en une seule fois et ne peut pas être modifiée. L’interface doit fonctionner sur mobile (responsive).

## **14.3 Interface de Projection (Écran public)**

**Objectif**

Afficher publiquement le déroulement du jeu sur un écran externe (vidéoprojecteur, télévision, écran géant). Cette interface est destinée au public / spectateurs et ne permet aucune interaction.

**Contenu affiché**

•        Nom et numéro de la manche en cours avec ses règles (affichées brièvement au début de chaque manche).

•        La question en cours : texte de la question \+ média (image, vidéo, audio) en plein écran, affichée dès que l’administrateur lance.

•        Le décompte du timer en grand format, visible à distance.

•        La bonne réponse, affichée quand l’administrateur la révèle.

•        La liste des joueurs éliminés après chaque question (pseudos, nombre d’éliminés, animation visuelle).

•        Le nombre de joueurs restants en compétition.

•        Le montant de la cagnotte en temps réel avec animation de mise à jour.

•        Les transitions entre manches : écran récapitulatif, animation d’intro de la manche suivante.

•        En finale (Manche 8\) : affichage du choix (continuer / abandonner) et du résultat final.

**Ce qui n’est PAS affiché sur la projection**

•        Les réponses individuelles des joueurs avant la clôture de la question.

•        Les statistiques détaillées de l’administrateur.

•        Les informations privées des joueurs (e-mail, téléphone).

**Contraintes techniques**

•        L’interface doit être optimisée pour les grands écrans (résolution 1920×1080 minimum, mode plein écran).

•        Polices et éléments suffisamment grands pour être lisibles à distance.

•        Aucun élément interactif (pas de boutons, pas de formulaires, pas de curseur).

•        Connexion WebSocket dédiée avec rôle « projection » (pas d’authentification joueur).

•        Reconnexion automatique en cas de perte de connexion, avec reprise de l’état en cours.

•        Accès via une URL dédiée (ex. : /session/{id}/projection) avec un code d’accès simple.

## **14.4 Synthèse des 3 interfaces**

| Caractéristique | Admin | Joueur | Projection |
| ----- | ----- | ----- | ----- |
| Type | Pilotage interactif | Participation interactive | Affichage lecture seule |
| Voit la question avant lancement | Oui | Non | Non |
| Peut répondre aux questions | Non | Oui | Non |
| Voit les réponses des joueurs | Oui (en temps réel) | Sa propre réponse uniquement | Non (avant clôture) |
| Affiche les éliminés | Oui (tableau de bord) | Notification personnelle | Oui (liste publique) |
| Contrôle le rythme | Oui (exclusif) | Non | Non |
| Accès | Login admin sécurisé | Lien unique JWT | URL \+ code d’accès |
| Responsive mobile | Optionnel (desktop préféré) | Oui (obligatoire) | Non (grand écran uniquement) |

# **15\. Exigences techniques**

## **15.1 Architecture**

| Composant | Technologie recommandée | Justification |
| ----- | ----- | ----- |
| Front-end | Next.js | Interfaces réactives, rendu rapide, écosystème riche |
| Back-end | Laravel | Gestion des WebSockets, performance, scalabilité |
| Base de données | MySQL | Données relationnelles \+ cache temps réel |
| Temps réel | Socket.IO ou WebSocket natif | Communication bidirectionnelle persistante |
| Hébergement | – | Scalabilité horizontale, CDN, haute disponibilité |

## **15.2 Performances**

•        Le temps de réponse serveur doit être inférieur à 200ms pour les événements WebSocket.

•        Le système doit supporter au moins 500 joueurs connectés simultanément par session.

•        Le temps de chargement initial de l’application ne doit pas excéder 3 secondes.

•        La base de données doit garantir la cohérence transactionnelle (ACID) pour les opérations critiques (scoring, éliminations).

## **15.3 Compatibilité**

•        Navigateurs : Chrome, Firefox, Safari, Edge (2 dernières versions majeures).

•        Mobile : iOS 15+ et Android 12+ (application web responsive ou application native).

•        Résolution minimale : 360px de large (mobile) et 1024px (desktop).

# **16\. Exigences non fonctionnelles**

## **16.1 Disponibilité**

Le système doit garantir une disponibilité de 99,5% en dehors des périodes de maintenance planifiée. Pendant une session de jeu active, aucune maintenance ne doit être effectuée.

## **16.2 Scalabilité**

L’architecture doit permettre la montée en charge horizontale pour gérer plusieurs sessions simultanées et un nombre croissant de joueurs inscrits.

## **16.3 Accessibilité**

•        L’application doit respecter les standards WCAG 2.1 niveau AA.

•        Navigation au clavier complète.

•        Contraste suffisant pour la lisibilité.

•        Compatibilité avec les lecteurs d’écran pour les interfaces principales.

## **16.4 Internationalisation**

L’application doit être conçue pour supporter le français comme langue principale, avec une architecture permettant l’ajout aisé d’autres langues (anglais, etc.) ultérieurement.

## **16.5 Sauvegarde et récupération**

•        Sauvegardes automatiques quotidiennes de la base de données.

•        Sauvegarde incrémentale de l’état de chaque session en cours (pour permettre la reprise en cas de panne).

•        Rétention des sauvegardes : 30 jours minimum.

# **17\. Sécurité et conformité**

## **17.1 Authentification et autorisation**

•        Authentification de l’administrateur par e-mail et mot de passe .

•        Authentification des joueurs par lien unique.

•        Séparation stricte des rôles (RBAC) : administrateur, joueur, spectateur.

## **17.2 Anti-triche**

•        Les réponses ne peuvent être soumises qu’une seule fois par question.

•        Le timer est géré côté serveur (le client ne peut pas manipuler le temps).

•        Détection des soumissions multiples depuis le même compte.

•        Vérification de l’empreinte du navigateur pour détecter les sessions dupliquées.

•        Chiffrement des réponses en transit (HTTPS/WSS).

## **17.3 Protection des données**

•        Conformité RGPD pour les données personnelles des joueurs.

•        Chiffrement des données sensibles au repos (mots de passe, tokens).

•        Politique de rétention des données : suppression automatique des données personnelles 90 jours après la fin d’une session.

•        Droit d’accès, de rectification et de suppression pour les joueurs.

# **18\. Livrables et jalons**

## **18.1 Phases du projet**

| Phase | Livrables | Durée estimée |
| ----- | ----- | ----- |
| Phase 1 — Conception | Maquettes UI/UX, diagrammes d’architecture, modèle de données, spécifications API | \- semaines |
| Phase 2 — Développement Back-end | API REST, moteur de jeu, WebSocket, base de données, système de scoring | \- semaines |
| Phase 3 — Développement Front-end | Interface admin, salle de jeu, inscription, tableau de bord | \- semaines |
| Phase 4 — Intégration & Tests | Tests unitaires, tests d’intégration, tests de charge, corrections | \- semaines |
| Phase 5 — Recette et Déploiement | Tests utilisateurs, corrections finales, mise en production, documentation | \- semaines |

## **18.2 Documentation**

•        Documentation technique (architecture, API, modèle de données).

•        Guide utilisateur administrateur.

•        Guide utilisateur joueur.

•        Plan de tests et rapports de tests.

•        Documentation de déploiement et d’exploitation.

 

