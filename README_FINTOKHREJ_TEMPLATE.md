# Fin Tokhroj — intégration template Symfony

Cette version du projet contient :

- un front office Symfony inspiré de l'application Java fournie ;
- un back office Symfony inspiré du dashboard Java fourni ;
- une palette visuelle harmonisée avec le logo ;
- des pages branchées sur la base existante via Doctrine DBAL pour éviter de bloquer l'affichage en cas de mappings d'entités imparfaits issus du reverse engineering.

## Pages ajoutées

### Front
- `/`
- `/lieux`
- `/sorties`
- `/offres`
- `/evenements`

### Admin
- `/admin`
- `/admin/users`
- `/admin/lieux`
- `/admin/sorties`
- `/admin/offres`
- `/admin/evenements`

## Fichiers principaux

- `src/Controller/FrontController.php`
- `src/Controller/AdminController.php`
- `templates/front/*`
- `templates/admin/*`
- `public/theme/css/fintokhrej.css`
- `public/theme/js/app.js`
- `public/theme/images/logo.png`

## Important

Le projet de reverse engineering initial contient des entités générées automatiquement. Certaines relations Doctrine issues du reverse engineering peuvent nécessiter une correction plus fine si tu veux aller vers un CRUD complet avec formulaires Doctrine. La version livrée ici privilégie l'intégration visuelle complète et l'affichage fiable des données.