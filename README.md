# Documentation API IllDetect

## Configuration initiale

### 1. Installation de la base de données

```bash
# Importer le fichier SQL dans MySQL
mysql -u root -p < backend/database.sql
```

### 2. Configuration

Éditer `backend/config.php` avec vos paramètres de base de données :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'illdetect');
define('DB_USER', 'root');
define('DB_PASS', 'votre_mot_de_passe');
```

### 3. Structure des dossiers

```
backend/
├── config.php              # Configuration et connexion DB
├── database.sql            # Script de création de la base de données
├── .htaccess              # Configuration Apache
└── api/
    ├── auth.php           # Authentification
    ├── reports.php        # Gestion des signalements
    ├── stats.php          # Statistiques
    ├── alerts.php         # Gestion des alertes
    ├── communes.php       # Données des communes
    └── settings.php       # Paramètres système
```

---

## Endpoints API

### Base URL
```
http://localhost/backend/api/
```

---

## 1. Authentification

### POST `/api/auth.php` - Login Admin
**Body:**
```json
{
  "email": "admin@illdetect.com",
  "password": "admin123"
}
```

**Réponse:**
```json
{
  "success": true,
  "message": "Connexion réussie",
  "data": {
    "token": "admin_token_abc123",
    "user": {
      "id": 1,
      "name": "Administrateur",
      "email": "admin@illdetect.com",
      "role": "admin"
    }
  }
}
```

### GET `/api/auth.php` - Vérifier la session
**Headers:**
```
Authorization: Bearer admin_token_abc123
```

### DELETE `/api/auth.php` - Logout
**Headers:**
```
Authorization: Bearer admin_token_abc123
```

---

## 2. Signalements

### GET `/api/reports.php` - Liste des signalements
**Query params:**
- `commune` (optionnel): Filtrer par commune
- `status` (optionnel): normal | alerte
- `search` (optionnel): Recherche par nom, email ou commune
- `limit` (optionnel, défaut: 100)
- `offset` (optionnel, défaut: 0)

**Exemple:**
```
GET /api/reports.php?commune=Abobo&status=alerte&limit=10
```

**Réponse:**
```json
{
  "success": true,
  "data": {
    "reports": [
      {
        "id": 1,
        "user_id": 2,
        "user_name": "Kouassi Jean",
        "user_email": "kouassi@email.com",
        "commune": "Abobo",
        "symptoms": ["Fièvre", "Toux", "Fatigue"],
        "other_symptoms": "",
        "latitude": "5.4278",
        "longitude": "-3.9975",
        "report_date": "2026-02-28",
        "status": "alerte",
        "created_at": "2026-02-28 10:30:00"
      }
    ],
    "total": 94
  }
}
```

### POST `/api/reports.php` - Créer un signalement
**Body:**
```json
{
  "name": "Kouassi Jean",
  "email": "kouassi@email.com",
  "commune": "Abobo",
  "symptoms": ["Fièvre", "Toux", "Fatigue"],
  "otherSymptoms": "Légère douleur thoracique",
  "date": "2026-02-28",
  "latitude": "5.4278",
  "longitude": "-3.9975"
}
```

**Réponse:**
```json
{
  "success": true,
  "message": "Signalement enregistré avec succès",
  "data": {
    "report_id": 15
  }
}
```

### PUT `/api/reports.php` - Mettre à jour un signalement (Admin)
**Headers:**
```
Authorization: Bearer admin_token_abc123
```

**Body:**
```json
{
  "id": 15,
  "status": "alerte"
}
```

### DELETE `/api/reports.php?id=15` - Supprimer (Admin)
**Headers:**
```
Authorization: Bearer admin_token_abc123
```

---

## 3. Statistiques

### GET `/api/stats.php` - Statistiques globales (Admin)
**Headers:**
```
Authorization: Bearer admin_token_abc123
```

**Réponse:**
```json
{
  "success": true,
  "data": {
    "total_reports": 94,
    "active_users": 67,
    "alert_zones": 3,
    "communes_tracked": 13,
    "reports_last_24h": 12,
    "top_communes": [
      {
        "commune": "Yopougon",
        "count": 18,
        "status": "alerte"
      }
    ],
    "recent_reports": [...],
    "reports_evolution": [
      {
        "date": "2026-02-22",
        "count": 8
      }
    ],
    "top_symptoms": {
      "Fièvre": 45,
      "Toux": 32,
      "Fatigue": 28
    }
  }
}
```

---

## 4. Alertes

### GET `/api/alerts.php` - Liste des alertes (Admin)
**Headers:**
```
Authorization: Bearer admin_token_abc123
```

**Query params:**
- `status` (optionnel): active | resolved

**Réponse:**
```json
{
  "success": true,
  "data": {
    "alerts": [
      {
        "id": 1,
        "commune": "Yopougon",
        "reports_count": 18,
        "threshold": 10,
        "status": "active",
        "created_at": "2026-02-28 08:00:00",
        "resolved_at": null
      }
    ]
  }
}
```

### POST `/api/alerts.php` - Créer une alerte (Admin)
**Headers:**
```
Authorization: Bearer admin_token_abc123
```

**Body:**
```json
{
  "commune": "Cocody"
}
```

### PUT `/api/alerts.php` - Résoudre une alerte (Admin)
**Headers:**
```
Authorization: Bearer admin_token_abc123
```

**Body:**
```json
{
  "id": 1
}
```

### DELETE `/api/alerts.php?id=1` - Supprimer (Admin)

---

## 5. Communes

### GET `/api/communes.php` - Données des communes
**Réponse:**
```json
{
  "success": true,
  "data": {
    "communes": [
      {
        "name": "Abobo",
        "latitude": "5.4278",
        "longitude": "-3.9975",
        "reports": 15,
        "status": "alerte"
      }
    ],
    "threshold": 10
  }
}
```

---

## 6. Paramètres

### GET `/api/settings.php` - Récupérer le seuil d'alerte
**Réponse:**
```json
{
  "success": true,
  "data": {
    "threshold": 10
  }
}
```

### PUT `/api/settings.php` - Mettre à jour le seuil (Admin)
**Headers:**
```
Authorization: Bearer admin_token_abc123
```

**Body:**
```json
{
  "threshold": 15
}
```

---

## Codes d'erreur

- `200`: Succès
- `201`: Créé
- `400`: Données invalides
- `401`: Non autorisé
- `404`: Non trouvé
- `500`: Erreur serveur

## Notes de sécurité

1. **Mot de passe par défaut**: Changez le mot de passe admin en production
2. **HTTPS**: Utilisez HTTPS en production
3. **Tokens**: Implémentez JWT pour une meilleure sécurité
4. **Rate limiting**: Ajoutez une limitation de taux pour éviter les abus
5. **Validation**: Validez et échappez toutes les entrées utilisateur
6. **Logs**: Implémentez un système de logging pour tracer les actions

## Compte de test

**Admin:**
- Email: `admin@illdetect.com`
- Mot de passe: `admin123`
