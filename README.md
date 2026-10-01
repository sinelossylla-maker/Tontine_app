# Backend Laravel — Tontine SHEKELE (Partie 2)

Service backend microservices Laravel 11 / PHP 8.2+ dédié à la **gestion des groupes de tontine, l'attribution des rangs de passage, le moteur financier de cotisations Mobile Money, les calculs de ponctualité, le virement automatique des cagnottes (Payouts) et les notifications utilisateur**.

---

## 🏛️ Architecture & Rôle du Service

Le backend Laravel fonctionne en synergie avec le microservice d'authentification et KYC développé sous **Django REST Framework**.

```text
                                  ┌──────────────────────────────────────────────┐
                                  │   Microservice Django REST Framework        │
                                  │  (Authentification JWT & Validation KYC)     │
                                  └──────────────────────┬───────────────────────┘
                                                         │ Emission Token JWT (HS256)
                                                         ▼
┌────────────────────────────────────────────────────────────────────────────────────────────────┐
│   Backend Laravel — Tontine SHEKELE (Partie 2)                                                 │
│                                                                                                │
│  ├── [Middleware JWT]        ValidateDjangoJwt (Décodes HS256, contrôle `is_kyc_verified=true`) │
│  ├── [Gestion des Groupes]   Tontines, Membres, Adhésions via Code d'Invitation Unique          │
│  ├── [Moteur Financier]      Tours de Rotation, Cotisations Mobile Money (Wave, Orange, MTN)  │
│  ├── [Logique de Ponctualité] Évaluation automatique `on_time` (à temps) vs `late` (en retard)  │
│  ├── [Payouts Automatiques]   Remise atomique de la cagnotte au bénéficiaire & Passage au Tour  │
│  └── [Système Notifications]  Notifications en DB, canaux d'alertes & gestion des préférences  │
└────────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 📋 Prérequis Système

- **PHP** >= 8.2 (PHP 8.4 supporté)
- **Composer** >= 2.x
- **Extension Database** : Driver `pdo_mysql` (MySQL/MariaDB) ou `pdo_sqlite`
- **Extensions PHP requises** : `json`, `mbstring`, `openssl`, `bcmath`

---

## 📦 Guide d'Installation (Livraison Archive ZIP)

Ce projet est fourni directement sous la forme d'une archive ZIP prête à l'emploi.

### 1. Décompression de l'archive ZIP
Extraire l'archive téléchargée dans votre répertoire de travail local :
```bash
unzip tontine_shekele_laravel.zip
cd Tontine_SHEKELE
```

### 2. Installation des dépendances Composer
Installez les dépendances du projet (incluant `firebase/php-jwt`) :
```bash
composer install
```

### 3. Configuration du fichier d'environnement
Copiez le fichier de configuration d'exemple et générez la clé d'application Laravel :
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configuration critique du `JWT_SECRET` (Partagé avec Django)
Le middleware d'authentification Laravel valide les tokens Bearer générés par Django REST Framework via un algorithme symétrique **HS256**. 

Assurez-vous de définir la même clé secrète que le backend Django dans le fichier `.env` :
```ini
JWT_SECRET=django-secret-key-tontine-shekele-2026
JWT_ALGO=HS256
```

---

## 🗄️ Base de Données & Seeder Réel (`TestTontineSeeder`)

Exécutez la commande suivante pour réinitialiser la base de données et injecter le jeu de données réelles de test :
```bash
php artisan migrate:fresh --seed
```

### Détail des 4 Tontines de Test créées en base :

| Tontine | Statut | Membres | Description |
| :--- | :--- | :--- | :--- |
| **Tontine Abidjan Espoir** | `pending` | 1 | Groupe en attente d'adhésions via son code d'invitation unique. |
| **Tontine Cocody Business** | `active` | 3 | Groupe complet (3/3), Tour 1 actif avec un versement à temps (`on_time`) enregistré via Wave. |
| **Tontine Marcory Solidarité** | `active` | 2 | Tour 1 actif avec un versement enregistré en retard (`late`) via Orange Money. |
| **Tontine Plateau Succès** | `completed` | 2 | Cycle entièrement clôturé avec l'ensemble des versements et payouts exécutés (`payout_status = paid`). |

---

## 🧪 Exécution de la Suite de Tests & Vérification QA

Une suite complète de tests de bout en bout et d'assurance qualité (couvrant 100% des conditions et cas limites) est disponible.

Exécutez le runner QA automatisé :
```bash
php tests/run_tests.php
```

### Rapport d'exécution QA (28/28 PASS - 0 Échec) :
```text
=================================================================================
 TONTINE SHEKELE PART 2 - EXHAUSTIVE QA AUTOMATION & AUDIT TEST SUITE
=================================================================================

--- Section 1: Security & JWT Validation ---
 [PASS] Test 1: Missing Bearer token is rejected with 401 Unauthorized
 [PASS] Test 2: Invalid JWT signature is rejected with 401 Unauthorized
 [PASS] Test 3: KYC unverified user is forbidden with 403

--- Section 2: Group Creation & Membership Edge Cases ---
 [PASS] Test 4: Creating a tontine group returns 201 Created
 [PASS] Test 5: Rejection on non-existent invitation code returns 404
 [PASS] Test 6: Member 2 joins tontine and is assigned turn_order = 2
 [PASS] Test 7: Rejection when joining full tontine returns 422
 [PASS] Test 8: Rejection when member attempts duplicate join returns 422

--- Section 3: Financial Engine - Starting Tontine & Rounds ---
 [PASS] Test 9: Starting tontine generates schedule and returns 200 OK
 [PASS] Test 9b: Generated exactly 2 rotation rounds matching max_members = 2
 [PASS] Test 10: Rejection when starting already active tontine returns 400 Bad Request

--- Section 4: Mobile Money Contributions & Punctuality ---
 [PASS] Test 11: Contribution before due date receives punctuality_status = on_time
 [PASS] Test 12: Rejection on duplicate contribution for same round returns 400
 [PASS] Test 13: Rejection on duplicate transaction reference returns 422
 [PASS] Test 14: Rejection when paying on pending/inactive round returns 400
 [PASS] Test 15: Contribution after due date receives punctuality_status = late
 [PASS] Test 16a: Full collection triggers payout (payout_status = paid, status = completed)
 [PASS] Test 16b: Next round (Round 2) is automatically activated
 [PASS] Test 17: Finishing final round marks overall tontine status = completed

--- Section 5: Notifications & User Preferences ---
 [PASS] Test 18: Retrieve notifications list returns 200 OK
 [PASS] Test 19: Mark single notification as read returns 200 OK
 [PASS] Test 20: Mark all notifications as read returns 200 OK
 [PASS] Test 21a: Retrieve notification preferences returns 200 OK
 [PASS] Test 21b: Update notification preferences returns 200 OK with updated values

--- Section 6: Verification of Seeded Dataset ---
 [PASS] Test 22: Seeded Tontine A is in pending state
 [PASS] Test 23: Seeded Tontine B is active with 3 members
 [PASS] Test 24: Seeded Tontine C contains late contribution
 [PASS] Test 25: Seeded Tontine D is in completed state

=================================================================================
 QA Execution Finished: 28/28 assertions passed.
 ALL TESTS PASSED SUCCESSFULLY! (0 FAILURES)
=================================================================================
```

---

## 📡 Documentation des Endpoints API

Toutes les routes API sont protégées par le middleware `jwt.django` et requièrent le header HTTP :
`Authorization: Bearer <token_jwt_django>`

### Tableau Récapitulatif des Endpoints

| Méthode | URI | Description | Status Succès |
| :--- | :--- | :--- | :--- |
| `POST` | `/api/tontines` | Créer un groupe de tontine (créateur = rang 1) | `201 Created` |
| `GET` | `/api/tontines/{id}` | Obtenir les détails d'une tontine et ses membres ordonnés par rang | `200 OK` |
| `POST` | `/api/tontines/join` | Rejoindre une tontine via son code d'invitation 8 caractères | `201 Created` |
| `GET` | `/api/my-tontines` | Lister les tontines auxquelles appartient l'utilisateur connecté | `200 OK` |
| `POST` | `/api/tontines/{tontineId}/start` | Démarrer la tontine et générer le calendrier des tours | `200 OK` |
| `GET` | `/api/tontines/{tontineId}/rounds` | Lister tous les tours de rotation d'une tontine | `200 OK` |
| `POST` | `/api/rounds/{roundId}/pay` | Enregistrer une cotisation Mobile Money (`wave`, `orange_money`, `mtn_momo`) | `201 Created` |
| `GET` | `/api/rounds/{roundId}/contributions` | Lister les cotisations d'un tour avec statut de ponctualité | `200 OK` |
| `GET` | `/api/notifications` | Lister les notifications de l'utilisateur connecté | `200 OK` |
| `POST` | `/api/notifications/{id}/read` | Marquer une notification comme lue par son UUID | `200 OK` |
| `POST` | `/api/notifications/read-all` | Marquer toutes ses notifications comme lues | `200 OK` |
| `GET` | `/api/notifications/preferences` | Consulter ses préférences de notifications | `200 OK` |
| `PUT` | `/api/notifications/preferences` | Mettre à jour ses préférences de notifications | `200 OK` |

---

### Exemples de Payloads JSON & Réponses API

#### 1. Création d'un groupe de tontine (`POST /api/tontines`)
**Request Payload:**
```json
{
  "name": "Tontine Solidarité Abidjan",
  "contribution_amount": 50000,
  "frequency": "monthly",
  "start_date": "2026-10-01",
  "max_members": 5
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Groupe de tontine créé avec succès.",
  "data": {
    "id": 1,
    "creator_phone": "+2250701020304",
    "name": "Tontine Solidarité Abidjan",
    "contribution_amount": "50000.00",
    "frequency": "monthly",
    "start_date": "2026-10-01T00:00:00.000000Z",
    "max_members": 5,
    "invitation_code": "XKFQJWYJ",
    "status": "pending",
    "members": [
      {
        "id": 1,
        "tontine_id": 1,
        "member_phone": "+2250701020304",
        "turn_order": 1,
        "status": "active",
        "joined_at": "2026-09-23T18:00:00.000000Z"
      }
    ]
  }
}
```

#### 2. Rejoindre une tontine (`POST /api/tontines/join`)
**Request Payload:**
```json
{
  "invitation_code": "XKFQJWYJ"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Vous avez rejoint la tontine avec succès.",
  "data": {
    "tontine_id": 1,
    "tontine_name": "Tontine Solidarité Abidjan",
    "member": {
      "id": 2,
      "tontine_id": 1,
      "member_phone": "+2250505060708",
      "turn_order": 2,
      "status": "active",
      "joined_at": "2026-09-23T18:05:00.000000Z"
    }
  }
}
```

#### 3. Enregistrer un versement Mobile Money (`POST /api/rounds/{roundId}/pay`)
**Request Payload:**
```json
{
  "payment_provider": "wave",
  "amount": 50000,
  "transaction_reference": "WAVE_TXN_20260923_8899"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Versement enregistré avec succès.",
  "data": {
    "id": 10,
    "tontine_round_id": 1,
    "member_phone": "+2250701020304",
    "amount": "50000.00",
    "payment_provider": "wave",
    "transaction_reference": "WAVE_TXN_20260923_8899",
    "payment_timestamp": "2026-09-23T18:10:00.000000Z",
    "punctuality_status": "on_time",
    "payment_status": "successful"
  }
}
```

---

## 🚀 Démarrage du Serveur de Développement Local

Pour démarrer le serveur de développement local Laravel :
```bash
php artisan serve
```
Le service backend sera accessible sur `http://127.0.0.1:8000`.
