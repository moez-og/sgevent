# Module Événements v2 — Guide d'installation

## Fichiers à copier

Copiez chaque fichier dans votre projet Symfony **en écrasant l'existant** :

```
deliverables/
├── .env.patch                                    ← AJOUTER le contenu à votre .env
├── config/services.yaml                          ← REMPLACER
├── public/theme/css/evenement-enhance.css         ← NOUVEAU
├── src/
│   ├── Controller/
│   │   ├── Admin/EvenementController.php         ← REMPLACER
│   │   └── Front/RecommendationController.php    ← NOUVEAU
│   └── Service/
│       ├── EvenementService.php                  ← REMPLACER
│       └── RecommendationService.php             ← NOUVEAU
└── templates/
    ├── admin/evenement/
    │   ├── calendar.html.twig                    ← REMPLACER
    │   ├── edit.html.twig                        ← REMPLACER
    │   ├── index.html.twig                       ← REMPLACER
    │   ├── new.html.twig                         ← REMPLACER
    │   └── show.html.twig                        ← REMPLACER
    └── front/evenement/
        ├── index.html.twig                       ← REMPLACER
        └── recommendations.html.twig             ← NOUVEAU
```

## Étapes d'installation

### 1. Copier les fichiers
```bash
# Depuis la racine de votre projet Symfony
cp -r deliverables/src/* src/
cp -r deliverables/templates/* templates/
cp -r deliverables/public/* public/
cp deliverables/config/services.yaml config/services.yaml
```

### 2. Ajouter la variable d'environnement
Ajoutez cette ligne à votre fichier `.env` :
```
GEMINI_API_KEY=CHANGE_ME
```

> **Note** : La clé Gemini est OPTIONNELLE. Si absente ou `CHANGE_ME`,
> le système utilise un algorithme local TF-IDF (100% gratuit, aucune API).
> Pour l'obtenir gratuitement : https://aistudio.google.com → "Get API Key"

### 3. Vider le cache
```bash
php bin/console cache:clear
```

### 4. Aucune migration requise
Aucun changement de schéma de base de données n'est nécessaire.

---

## Résumé des fonctionnalités ajoutées / modifiées

### 1. Gestion de participation automatique
- ❌ Plus de validation admin requise
- ✅ Inscription → CONFIRMEE automatiquement → paiement → PAYEE
- Les boutons "accepter/refuser" admin sont conservés mais non obligatoires
- Le refus reste possible (cas d'abus/fraude)

### 2. Génération d'image IA (Back-office)
- Bouton stylé `✨ Générer image (IA)` sur les formulaires new ET edit
- Utilise Pollinations AI (gratuit, pas de clé requise)
- Fallback HuggingFace si token configuré
- Spinner + animation pendant la génération

### 3. Calendrier (Back-office)
- FullCalendar 6.1.11 déjà intégré (existant, conservé)
- Vue mensuelle, hebdomadaire, liste
- Events colorés par statut

### 4. Recommandation intelligente (Front-office)
- **Nouveau** : `RecommendationService` avec 3 modes :
  1. Google Gemini 2.0 Flash (gratuit, optionnel)
  2. TF-IDF local (100% offline, par défaut)
  3. Popularité (fallback si aucun historique)
- Bannière IA stylée sur `/evenements`
- Panel inline avec chips d'intérêts + suggestions
- Page dédiée `/evenements/recommandations`
- Endpoint JSON `/evenements/recommandations/json`

### 5. Intégration météo (Front + Back)
- **Cartes météo** style iOS glassmorphism (dégradés bleus, icônes SVG)
- Affichées dans le panneau admin split-view (chargement async)
- Affichées dans la fiche détail (`show.html.twig`)
- Météo compacte (`wx-chip`) dans les recommandations
- API Open-Meteo (gratuit, pas de clé)

### 6. Back-office — UI améliorée
- Vue split-view conservée (liste → détails)
- Navigation clavier ↑↓ Home/End Enter
- Classe `is-selected` avec surbrillance
- Chevron indicateur `›` sur chaque ligne
- Weather cards strip horizontale scrollable

### 7. Front-office — Cards modernes
- Nouveau composant `.ev-card` avec :
  - Cover image avec zoom au hover
  - Badges superposés (gratuit/payant/bientôt complet)
  - Titre serif + description tronquée
  - Meta chips (date, lieu, places)
  - CTA stylé
  - Animation cascade au chargement
- Bannière IA avec gradient animé

### 8. Design global
- Fichier CSS additif `evenement-enhance.css` (ne touche pas `fintokhrej.css`)
- Variables CSS cohérentes (`--ev-*`)
- Animations spring modernes
- Responsive mobile
- Support dark mode via variables existantes

---

## Routes ajoutées

| Route | Méthode | Description |
|-------|---------|-------------|
| `/evenements/recommandations` | GET | Page recommandations (auth) |
| `/evenements/recommandations/json` | GET | JSON recommandations (auth) |
| `/admin/evenements/{id}/weather-cards` | GET | JSON weather cards (admin) |

## Fichiers NON modifiés

Les fichiers suivants n'ont **PAS** été touchés :
- `fintokhrej.css` (CSS principal)
- `base.html.twig`, `base_admin.html.twig`, `base_front.html.twig`
- Tous les autres modules (sorties, lieux, offres, users, etc.)
- Entités `Evenement.php`, `Inscription.php`, `User.php`, etc.
- `HfImageService.php`, `WeatherService.php`
- Toutes les routes existantes fonctionnent toujours
