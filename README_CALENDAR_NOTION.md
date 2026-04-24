# Module Calendrier + Notion Sync v2 — Guide d'installation

## Résumé des changements

### Ce qui a été fait

**1. Calendrier FullCalendar professionnel (Back-office)**
- Vue mois / semaine / liste avec navigation fluide
- Locale française, premier jour = lundi
- Indicateur "aujourd'hui" stylé (cercle gradient)
- Couleurs par statut (ouvert = vert, fermé = orange, annulé = rouge)
- Légende visuelle intégrée
- Tooltip natif au hover (titre • lieu • prix)
- **Popup de détails** au clic : image, métadonnées, description, lien vers fiche
- Fermeture par Échap, clic overlay, ou bouton ×
- Animations d'entrée (slide-in, scale pop)

**2. Synchronisation Notion (inspirée du Java)**
- **Panneau Notion intégré** dans la page calendrier avec :
  - Indicateur de statut en temps réel (connecté / erreur / en cours)
  - Bouton "Tester" : vérifie la connexion, détecte le schéma, crée les colonnes manquantes
  - Bouton "Synchroniser" : sync complète avec retour détaillé (créés, mis à jour, supprimés, échecs)
  - Résultats affichés avec badges animés et liste d'erreurs
- **NotionService.php enrichi** (inspiré de `NotionCalendarService.java`) :
  - `testConnection()` → vérifie la DB, détecte la propriété titre, auto-crée les colonnes manquantes
  - `syncAll()` → upsert par EventID, nettoyage des orphelins Notion
  - Fallback `SymfonyId` pour compatibilité avec l'ancien schéma
  - Pagination Notion (gère les bases de plus de 100 pages)
- **3 nouvelles routes** :
  - `POST /admin/evenements/notion-test` → test connexion (JSON)
  - `POST /admin/evenements/notion-sync-json` → sync complète (JSON)
  - `POST /admin/evenements/notion-sync` → sync classique (redirect + flash)

**3. Design professionnel**
- Glassmorphism cohérent avec le thème existant
- Typographie Fraunces + Manrope
- Animations spring (slide-in, pop-in, pulse, spin)
- Responsive mobile
- Palette cohérente (variables `--ev-*`)

## Fichiers à copier

```
cal_output/
├── config/services.yaml                          ← REMPLACER (supprime NotionClient, ajoute NotionService)
├── src/
│   ├── Controller/Admin/EvenementController.php  ← REMPLACER (nouvelles routes Notion + calendar-data enrichi)
│   └── Service/
│       ├── NotionService.php                     ← REMPLACER (v2 complète : test, syncAll, auto-schema)
│       └── NotionSyncService.php                 ← REMPLACER (délègue à NotionService)
└── templates/admin/evenement/
    └── calendar.html.twig                        ← REMPLACER (calendrier pro + panneau Notion)
```

## Installation

### 1. Copier les fichiers
```bash
cp cal_output/config/services.yaml config/services.yaml
cp cal_output/src/Service/NotionService.php src/Service/NotionService.php
cp cal_output/src/Service/NotionSyncService.php src/Service/NotionSyncService.php
cp cal_output/src/Controller/Admin/EvenementController.php src/Controller/Admin/EvenementController.php
cp cal_output/templates/admin/evenement/calendar.html.twig templates/admin/evenement/calendar.html.twig
```

### 2. Vérifier le .env
```env
NOTION_TOKEN="ntn_votre_token_ici"
NOTION_DATABASE_ID="votre_database_id_32_chars"
```

### 3. Supprimer NotionClient (si le fichier existe)
```bash
rm -f src/Service/NotionClient.php
```

### 4. Vider le cache
```bash
php bin/console cache:clear
```

### 5. Aucune migration requise

## Configuration Notion (une seule fois)

1. Aller sur https://www.notion.so/my-integrations
2. Cliquer "New integration" → nommer "FinTokhrej Calendar"
3. Copier le token (commence par `ntn_...`) → coller dans `.env` comme `NOTION_TOKEN`
4. Dans Notion, créer une page "Full page database"
5. Partager la base avec l'intégration : `...` → `Connections` → choisir l'intégration
6. Copier l'ID de la base depuis l'URL (32 caractères) → coller comme `NOTION_DATABASE_ID`
7. Les colonnes sont **auto-créées** par le service (Date Début, Date Fin, Statut, Type, Lieu, Prix, Capacité, EventID, Description)

## Fichiers NON modifiés

- `fintokhrej.css`, `evenement-enhance.css`
- `base.html.twig`, `base_admin.html.twig`
- Tous les templates hors `calendar.html.twig`
- Tous les autres modules (sorties, lieux, offres, users)
- Entités (aucun changement de schéma)
