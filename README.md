# BiblioTech - Système de Gestion de Bibliothèque

![BiblioTech Logo](logo_bibliotech.png)

**Une solution moderne et complète pour la gestion de bibliothèque**

[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)](LICENSE)
[![Status](https://img.shields.io/badge/Status-Active-success?style=for-the-badge)]()

[Présentation](#présentation) | [Fonctionnalités](#fonctionnalités) | [Installation](#installation) | [Documentation](#documentation) | [Contribuer](#contribuer)

---

## Présentation

**BiblioTech** est une application web complète de gestion de bibliothèque développée en **PHP** et **MySQL**. Elle permet aux bibliothèques de gérer efficacement leur catalogue, leurs membres, leurs emprunts, leurs réservations et bien plus encore.

Conçue avec une architecture modulaire et une interface intuitive, BiblioTech s'adresse aussi bien aux petites bibliothèques associatives qu'aux établissements universitaires.

### Objectifs

- **Centraliser** la gestion du catalogue et des exemplaires
- **Simplifier** l'inscription et le suivi des membres
- **Automatiser** les emprunts, retours et prolongations
- **Gérer** les amendes et les paiements
- **Fournir** des rapports et statistiques détaillés
- **Notifier** les membres des échéances importantes

---

## Fonctionnalités

### Espace Membre

| Fonctionnalité | Description |
|----------------|-------------|
| **Catalogue en ligne** | Consulter et rechercher parmi tous les livres disponibles |
| **Emprunts** | Emprunter des livres selon les quotas attribués |
| **Réservations** | Réserver des livres indisponibles |
| **Prolongations** | Prolonger les emprunts en cours |
| **Gestion des amendes** | Consulter et payer les amendes en ligne |
| **Achats** | Commander des livres (physiques ou numériques) |
| **Évaluations** | Noter et commenter les livres lus |
| **Événements** | S'inscrire aux événements de la bibliothèque |
| **Notifications** | Recevoir des alertes personnalisées |

### Espace Staff / Admin

| Module | Fonctionnalités |
|--------|-----------------|
| **Gestion du catalogue** | Ajouter, modifier, supprimer des livres et exemplaires |
| **Gestion des membres** | Inscription, modification, suspension, quotas |
| **Gestion des emprunts** | Enregistrer emprunts et retours, prolonger |
| **Gestion des réservations** | Traiter les réservations, notifier les membres |
| **Gestion des amendes** | Créer, encaisser, annuler des amendes |
| **Actualités** | Publier des annonces et informations |
| **Événements** | Créer et gérer les événements |
| **Rapports** | Statistiques d'activité, exports PDF/CSV |
| **Paramètres** | Configuration complète de l'application |
| **Logs et Audit** | Traçabilité complète des actions |
| **Sauvegarde** | Export de la base de données |

### Fonctionnalités transverses

- Interface **responsive** (mobile, tablette, desktop)
- Authentification sécurisée avec **bcrypt**
- Support **multi-rôles** (Super Admin, Admin, Bibliothécaire, Assistant, Membre)
- **Recherche avancée** (titre, auteur, ISBN, catégorie, tags)
- **PWA Ready** (installable sur mobile)
- **Export/Import** de données
- **Mode sombre** (optionnel)

---

## Architecture Technique

### Stack Technologique

| Composant | Technologie |
|-----------|-------------|
| **Backend** | PHP 8.0+ (procédural + PDO) |
| **Base de données** | MySQL / MariaDB 10.4+ |
| **Frontend** | HTML5, CSS3, JavaScript (Vanilla) |
| **Serveur** | Apache (XAMPP, WAMP, LAMP) |
| **Gestion de version** | Git / GitHub |

### Structure du Projet

```
bibliotech/
├── admin/                    # Modules d'administration
│   ├── admin_dashboard.php
│   ├── gerer_emprunts.php
│   ├── gerer_membres.php
│   └── ...
│
├── membre/                   # Espace membre
│   ├── dashboard.php
│   ├── mes_emprunts.php
│   ├── mes_reservations.php
│   └── ...
│
├── public/                   # Pages publiques
│   ├── index.php
│   ├── catalogue.php
│   ├── details_livre.php
│   └── ...
│
├── includes/                 # Fichiers inclus
│   ├── config.php
│   ├── header.php
│   ├── footer.php
│   └── functions.php
│
├── assets/                   # Ressources statiques
│   ├── css/
│   ├── js/
│   ├── images/
│   └── uploads/
│
├── database/                 # Scripts SQL
│   ├── schema.sql
│   └── seed.sql
│
├── README.md
├── LICENSE
└── .gitignore
```

---

## Installation

### Prérequis

- **Serveur web** : Apache 2.4+ (XAMPP, WAMP, MAMP, LAMP)
- **PHP** : 8.0 ou supérieur
- **MySQL** : 5.7+ ou MariaDB 10.4+
- **Extensions PHP** : `pdo_mysql`, `mysqli`, `mbstring`, `openssl`, `gd`, `json`

### Installation Rapide

#### 1. Cloner le dépôt

```bash
cd C:\xampp\htdocs
git clone https://github.com/dounia310/BiblioTech.git bibliotech
```

#### 2. Créer la base de données

```sql
CREATE DATABASE bibliotech CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 3. Importer le schéma

```bash
mysql -u root -p bibliotech < database/schema.sql
```

Ou via **phpMyAdmin** : `http://localhost/phpmyadmin` puis Importer

#### 4. Configurer la connexion

Éditez le fichier `includes/config.php` :

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'bibliotech');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// URL de base
define('BASE_URL', 'http://localhost/bibliotech/');
?>
```

#### 5. Lancer l'application

Ouvrez votre navigateur :

```
http://localhost/bibliotech/
```

### Comptes par défaut

| Rôle | Username | Mot de passe |
|------|----------|--------------|
| Super Admin | `superadmin` | `admin123` |
| Admin | `admin` | `admin123` |
| Bibliothécaire | `biblio1` | `biblio123` |
| Membre | `MEM20240001` | `membre123` |

**Important** : Changez ces mots de passe après la première connexion.

---

## Documentation

### Schéma de la Base de Données

#### Tables Principales

| Table | Description |
|-------|-------------|
| `livres` | Catalogue des livres (métadonnées) |
| `auteurs` | Auteurs avec biographies |
| `editeurs` | Maisons d'édition |
| `collections` | Collections éditoriales |
| `exemplaires` | Exemplaires physiques |
| `categories` | Catégories hiérarchiques |
| `tags` | Mots-clés transversaux |
| `membres` | Membres inscrits |
| `utilisateurs` | Personnel (staff) |
| `emprunts` | Historique des emprunts |
| `reservations` | Réservations en attente |
| `amendes` | Amendes et paiements |
| `evaluations` | Notes et commentaires |
| `evenements` | Événements programmés |
| `nouvelles` | Actualités |
| `notifications` | Alertes envoyées |
| `logs_audit` | Journal d'audit |
| `parametres` | Configuration système |

#### Relations Principales

```
livres --> auteurs (N:1)
livres --> editeurs (N:1)
livres --> collections (N:1)
livres --> categories (N:N via livre_categories)
livres --> tags (N:N via livre_tags)
livres --> exemplaires (1:N)

exemplaires --> emprunts (1:N)
membres --> emprunts (1:N)
membres --> reservations (1:N)
membres --> amendes (1:N)
membres --> evaluations (1:N)
```

### Rôles et Permissions

| Rôle | Permissions |
|------|-------------|
| **Super Admin** | Accès total, configuration système |
| **Admin** | Gestion complète sauf config système |
| **Bibliothécaire** | Gestion livres, membres, emprunts |
| **Assistant** | Gestion emprunts et retours uniquement |
| **Membre** | Consultation, emprunts, réservations |

### Workflow d'Emprunt

```
1. Membre recherche un livre         --> recherche.php
2. Membre consulte les détails       --> details_livre.php
3. Membre emprunte ou réserve        --> emprunter_livre.php
                                         reserver_livre.php
4. Staff valide l'emprunt            --> gerer_emprunts.php
5. Notification au membre            --> notification_manager.php
6. Retour du livre                   --> retour_livre.php
7. Calcul d'amende si retard         --> gerer_amendes.php
```

---

## Tests

### Tester en Local (XAMPP)

```bash
# 1. Démarrer XAMPP (Apache + MySQL)
# 2. Accéder à l'application
http://localhost/bibliotech/

# 3. Vérifier l'installation
http://localhost/bibliotech/test.php
```

### Checklist de Test

- [ ] Connexion staff fonctionne
- [ ] Connexion membre fonctionne
- [ ] Catalogue affiche les livres
- [ ] Recherche retourne des résultats
- [ ] Emprunt possible
- [ ] Retour enregistré
- [ ] Amende calculée correctement
- [ ] Réservation fonctionne
- [ ] Notifications envoyées
- [ ] Rapports générés

---

## Contribuer

Nous accueillons avec plaisir les contributions.

### Comment contribuer

1. **Fork** le projet
2. Créez une branche (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Commitez vos changements (`git commit -m 'Ajout nouvelle fonctionnalité'`)
4. Poussez la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. Ouvrez une **Pull Request**

### Standards de Code

- **PHP** : PSR-12
- **SQL** : Requêtes préparées obligatoires
- **Sécurité** : Validation et échappement systématiques
- **Commentaires** : En français, clairs et concis

### Signaler un Bug

Ouvrez une [issue](https://github.com/dounia310/BiblioTech/issues) avec :

- Description du bug
- Étapes pour reproduire
- Comportement attendu vs observé
- Captures d'écran si possible

---

## Sécurité

### Bonnes Pratiques Implémentées

- **Mots de passe** : Hashage bcrypt
- **SQL Injection** : Requêtes préparées (PDO)
- **XSS** : Échappement HTML systématique
- **CSRF** : Tokens de session
- **Sessions** : Régénération d'ID
- **Permissions** : Vérification par rôle

### Signaler une Vulnérabilité

Ne publiez **pas** les vulnérabilités dans les issues. Envoyez un email à :

**elhorredounia51@gmail.com**

---

## Statistiques du Projet

![GitHub stars](https://img.shields.io/github/stars/dounia310/BiblioTech?style=social)
![GitHub forks](https://img.shields.io/github/forks/dounia310/BiblioTech?style=social)
![GitHub issues](https://img.shields.io/github/issues/dounia310/BiblioTech)
![GitHub last commit](https://img.shields.io/github/last-commit/dounia310/BiblioTech)

---

## Roadmap

### Version 1.0 (Actuelle)

- [x] Gestion du catalogue
- [x] Gestion des membres
- [x] Emprunts et retours
- [x] Réservations
- [x] Amendes
- [x] Rapports

### Version 1.1 (En cours)

- [ ] API REST
- [ ] Notifications par email
- [ ] Mode sombre
- [ ] Export PDF amélioré

### Version 2.0 (Prévue)

- [ ] Application mobile
- [ ] Recommandations IA
- [ ] Lecture en ligne
- [ ] Multi-langue

---

## Licence

Ce projet est sous licence **MIT**. Voir le fichier [LICENSE](LICENSE) pour plus de détails.

```
MIT License

Copyright (c) 2026 Dounia El Horre

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## Auteurs

**Dounia El Horre**

- Développeuse Full-Stack
- Email : elhorredounia51@gmail.com
- GitHub : [@dounia310](https://github.com/dounia310)

---

## Remerciements

- À toutes les bibliothèques qui nous inspirent
- À la communauté PHP et Open Source
- Aux contributeurs de Bootstrap et des icônes Font Awesome
- Au café, fidèle compagnon de développement

---

## Support

Besoin d'aide ?

- **Documentation** : [Wiki du projet](https://github.com/dounia310/BiblioTech/wiki)
- **Questions** : [Discussions](https://github.com/dounia310/BiblioTech/discussions)
- **Bugs** : [Issues](https://github.com/dounia310/BiblioTech/issues)
- **Email** : elhorredounia51@gmail.com

---

### Si ce projet vous plaît, n'oubliez pas de lui donner une étoile

**Fait avec passion par Dounia El Horre**

[Retour en haut](#bibliotech---système-de-gestion-de-bibliothèque)

