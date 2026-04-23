# Configuration n8n pour l'analyse d'offres

## ✅ Côté Symfony - Configuration complète

Tout est configuré côté Symfony ! Les routes et templates sont en place.

### Routes créées :
1. **POST /admin/offres/analyze** - Reçoit les données de l'offre et envoie le webhook à n8n
2. **POST /admin/offres/analyze-callback** - Reçoit les résultats d'analyse de n8n et les stocke en session
3. **POST /admin/offres/dismiss-analysis** - Supprime une analyse de la session

### Variables d'environnement à configurer :
```bash
# Dans .env ou .env.local
N8N_OFFRE_ANALYZE_WEBHOOK_URL=https://ademf.app.n8n.cloud/webhook-test/...
SYMFONY_BASE_URL=http://localhost:8000  # ou votre URL réelle
```

---

## 📦 Configuration n8n - Structure du workflow

Voici comment structurer votre workflow n8n :

### **1. Webhook Trigger (déjà existant)**
- **URL** : `https://ademf.app.n8n.cloud/webhook-test/6b52a9a9-f2da-49a6-ae1b-2d19844d7246`
- **Méthode** : POST
- Reçoit :
  ```json
  {
    "source": "admin_offre_analyze_button",
    "sent_at": "2026-04-21T10:30:00+00:00",
    "admin_user_id": 1,
    "offre_id": 123,
    "tracking_id": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6",
    "offre": {
      "titre": "Offre Spéciale",
      "type": "Réduction",
      "pourcentage": 60,
      "description": "...",
      "date_debut": "2026-04-21",
      "date_fin": "2026-04-23",
      "statut": "ACTIVE",
      "lieu_id": 5
    }
  }
  ```

### **2. Set Node (optionnel - pour formater les données)**
Normalise les données si nécessaire.

### **3. OpenAI Chat Completion Node**
Envoie l'offre à ChatGPT pour analyse marketing.

**Prompt recommandé** :
```
Analyse l'offre suivante du point de vue marketing et retourne UNIQUEMENT du JSON valide, sans texte avant ou après.

Offre:
- Titre: {{$node.Webhook.json.offre.titre}}
- Type: {{$node.Webhook.json.offre.type}}
- Réduction: {{$node.Webhook.json.offre.pourcentage}}%
- Description: {{$node.Webhook.json.offre.description}}
- Dates: {{$node.Webhook.json.offre.date_debut}} à {{$node.Webhook.json.offre.date_fin}}

Retourne ce JSON EXACTEMENT:
{
  "score": <0-100>,
  "evaluation": "<texte court évaluant l'offre>",
  "points_faibles": ["<point faible 1>", "<point faible 2>"],
  "ameliorations": ["<amélioration 1>", "<amélioration 2>"],
  "offre_optimisee": {
    "titre": "<titre amélioré>",
    "description": "<description améliorée>",
    "pourcentage_suggere": <nombre 0-100>
  },
  "diffusion": {
    "canaux": ["<canal 1>", "<canal 2>"],
    "timing": "<moment optimal>",
    "public_cible": "<description du public>"
  }
}
```

**Configuration** :
- Model: `gpt-4-turbo` ou `gpt-3.5-turbo`
- Temperature: `0.7`
- Max tokens: `1000`

### **4. Function Node (Validation JSON optionnelle)**
Valide que la réponse est du JSON valide :

```javascript
try {
  const result = JSON.parse(items[0].json.text);
  // Vérifier que les champs requis existent
  if (!result.score || !result.evaluation) {
    throw new Error('Fields missing');
  }
  return result;
} catch (e) {
  return { error: 'Invalid JSON: ' + e.message };
}
```

### **5. HTTP Request Node (Callback à Symfony)**
Envoie les résultats d'analyse à Symfony.

**Configuration** :
- **Method** : POST
- **URL** : `${SYMFONY_BASE_URL}/webhook/offres/analyze-callback`
  - Remplacer `${SYMFONY_BASE_URL}` par `http://localhost:8000` en dev, ou votre URL réelle en prod
- **Headers** :
  ```
  Content-Type: application/json
  ```
- **Body (Raw)** :
  ```json
  {
    "offre_id": {{$node.Webhook.json.offre_id}},
    "tracking_id": "{{$node.Webhook.json.tracking_id}}",
    "score": {{$node["OpenAI Chat Completion"].json.score}},
    "evaluation": "{{$node["OpenAI Chat Completion"].json.evaluation}}",
    "points_faibles": {{$node["OpenAI Chat Completion"].json.points_faibles}},
    "ameliorations": {{$node["OpenAI Chat Completion"].json.ameliorations}},
    "offre_optimisee": {{$node["OpenAI Chat Completion"].json.offre_optimisee}},
    "diffusion": {{$node["OpenAI Chat Completion"].json.diffusion}}
  }
  ```

### **6. Respond to Webhook Node**
Retourne une réponse simple à Symfony :
- **Status Code** : `200`
- **Body** :
  ```json
  {
    "status": "success",
    "message": "Analysis received"
  }
  ```

---

## 🔧 Format des données reçues par Symfony

Quand n8n appelle `/admin/offres/analyze-callback`, le JSON ressemble à ceci :

```json
{
  "offre_id": 123,
  "score": 70,
  "evaluation": "L'offre est attrayante mais manque de détails clés.",
  "points_faibles": [
    "Lieu non défini",
    "Description insuffisante",
    "Période limitée"
  ],
  "ameliorations": [
    "Ajouter des informations sur le lieu",
    "Élargir la description de l'offre",
    "Considérer une période de validité plus longue"
  ],
  "offre_optimisee": {
    "titre": "Offre Spéciale : adem - 60% de Réduction",
    "description": "Profitez de 60% de réduction sur nos produits du 21 au 23 avril 2026. Ne ratez pas cette occasion unique !",
    "pourcentage_suggere": 70
  },
  "diffusion": {
    "canaux": ["réseaux sociaux", "emailing"],
    "timing": "1 mois avant le début de la période",
    "public_cible": "Jeunes adultes cherchant des bonnes affaires"
  }
}
```

---

## 🎯 Flux utilisateur complet

1. **Admin clique sur "📊 Analyser offre"** dans le formulaire de modification d'une offre
2. Symfony envoie les données de l'offre au webhook n8n
3. n8n reçoit les données et les traite via ChatGPT
4. n8n envoie les résultats d'analyse à Symfony via le callback
5. **Les résultats s'affichent dans la page admin** avec :
   - Score marketing (0-100, couleur-codé)
   - Évaluation générale
   - Points faibles
   - Améliorations suggérées
   - Version optimisée de l'offre
   - Recommandations de diffusion (canaux, timing, public cible)
6. **Admin peut utiliser les données** :
   - Copier/coller les valeurs proposées
   - Cliquer "Utiliser cette version" (à implémenter pour mise à jour auto)
   - Fermer le résultat

---

## 🐛 Troubleshooting

### Si les résultats n'apparaissent pas :
1. **Vérifier les logs n8n** : Les erreurs HTTP 400+ ou timeout apparaîtront
2. **Tester le callback manuellement** :
   ```bash
   curl -X POST http://localhost:8000/admin/offres/analyze-callback \
     -H "Content-Type: application/json" \
     -d '{
       "offre_id": 123,
       "score": 75,
       "evaluation": "Test",
       "points_faibles": [],
       "ameliorations": [],
       "offre_optimisee": {
         "titre": "Test",
         "description": "Test",
         "pourcentage_suggere": 50
       },
       "diffusion": {
         "canaux": ["test"],
         "timing": "test",
         "public_cible": "test"
       }
     }'
   ```
3. **Vérifier la session Symfony** : Les résultats sont stockés en session, donc nécessitent des cookies actifs

### Si les résultats disparaissent après rechargement :
C'est normal ! Ils sont en session temporaire. Pour persister, ajoutez une entité `OffreAnalysis` en base.

### Si le JSON de ChatGPT n'est pas valide :
Ajoutez plus de contexte dans le prompt et augmentez la température pour être plus strict.

---

## 📝 Notes

- **Sécurité** : Le callback n'utilise pas de CSRF car c'est un webhook externe
- **Performance** : Les analyses sont asynchrones (pas de blocage lors du clic)
- **Stockage** : Les données sont en session (RAM), à remplacer par une BDD si persistance requise
- **Améliorations futures** : Ajouter signature webhook, quotas, historique des analyses
