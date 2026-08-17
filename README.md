# Therapy - Gestion de cabinet de psychothérapie

![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Symfony 8.0](https://img.shields.io/badge/Symfony-8.0-000000?logo=symfony&logoColor=white)
![PostgreSQL 16](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)
![Redis 7](https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white)
![Astro 5](https://img.shields.io/badge/Astro-5.7-BC52EE?logo=astro&logoColor=white)
![Svelte 5](https://img.shields.io/badge/Svelte-5-FF3E00?logo=svelte&logoColor=white)
![Angular 21](https://img.shields.io/badge/Angular-21-DD0031?logo=angular&logoColor=white)
![Angular Material](https://img.shields.io/badge/Angular_Material-21-757575?logo=angular&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.4-06B6D4?logo=tailwindcss&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)

Application web pour la gestion d'un cabinet de psychothérapie individuel. Les visiteurs consultent les disponibilités et soumettent des demandes de rendez-vous. La thérapeute gère son planning, confirme ou annule les rendez-vous, et intègre ses patients via un système d'invitation.

Projet conçu à partir d'un besoin réel : le cabinet d'une psychothérapeute, sans outil de gestion.

**État du projet** : en développement. L'API est complète, la landing et le dashboard ne le sont pas encore. L'état à jour de chaque composant est dans [`docs/STATUS.md`](docs/STATUS.md) - c'est la référence, pas ce README.

---

## Fonctionnalités

**Côté visiteur / patient**

- Consultation des créneaux disponibles en temps réel, filtrables par modalité (en ligne / en personne)
- Formulaire de prise de rendez-vous avec email de confirmation automatique
- Inscription patient sur invitation uniquement (lien à usage unique, durée limitée)
- Réinitialisation de mot de passe par email

**Côté thérapeute - disponible dans le dashboard**

- Gestion des patients et des invitations (envoi, renvoi, révocation)
- Navigation par rôle, login thérapeute et patient, réinitialisation de mot de passe

**Côté thérapeute - implémenté côté API, interface pas encore construite**

- Planning hebdomadaire récurrent avec créneaux configurables par jour et par modalité
- Exceptions de planning (vacances, indisponibilités ponctuelles)
- Cycle de vie complet des rendez-vous : `REQUESTED → CONFIRMED → COMPLETED / CANCELLED`
- Création manuelle de rendez-vous pour les patients existants
- Email d'agenda quotidien (envoyé par cron)

**Fuseaux horaires**

Les instants sont stockés en UTC (`timestamptz`) et rendus dans le fuseau du lecteur. Le planning récurrent est ancré au fuseau du cabinet (`PRACTICE_TIMEZONE`), pas à celui du serveur : un bloc « lundi 09:00 » reste à 09:00 pour la thérapeute quel que soit le changement d'heure. Décisions détaillées dans [`docs/adr/`](docs/adr/).

---

## Architecture Backend - Hexagonale (Ports & Adapters)

Le backend suit une architecture hexagonale (Ports & Adapters) en 3 couches, avec une règle de dépendance unidirectionnelle :

```
         Côté Driving                                      Côté Driven
      (qui appelle l'app)                            (que l'app appelle)

┌───────────────────┐    ┌───────────────────┐    ┌───────────────────────┐
│  Infrastructure   │    │   Application     │    │       Domain          │
│                   │    │                   │    │                       │
│  HTTP Controllers ├───►│  Handlers         │    │  Entités              │
│  CLI Commands     │    │  (Use Cases)      ├───►│  Value Objects        │
│                   │    │                   │    │  Services métier      │
│                   │    │  DTOs             │    │  Exceptions           │
│                   │    │  Input / Output   │    │                       │
│                   │    └─────────┬─────────┘    │  Ports (Interfaces)   │
│                   │              │               │  ├─ Repositories     │
│  Adaptateurs      │              │ dépend de     │  └─ Services         │
│  ├─ Doctrine Repos◄─────────────┼───────────────┤                       │
│  ├─ Email Senders │              ▼               │                       │
│  ├─ Security      │        implémente            │  (zéro dépendance    │
│  └─ Redis         │         les ports            │   framework)         │
└───────────────────┘                              └───────────────────────┘

              Infrastructure ──► Application ──► Domain
                   (la dépendance pointe toujours vers l'intérieur)
```

### Couches

| Couche | Rôle | Contenu |
| ------ | ---- | ------- |
| **Domain** | Logique métier pure, aucune dépendance framework | Entités, Value Objects, interfaces Repository et Service (ports driven), services métier, exceptions |
| **Application** | Orchestration des cas d'usage | Handlers (1 fichier = 1 use case = 1 méthode `__invoke()`), DTOs Input/Output |
| **Infrastructure** | Adaptateurs techniques (driving + driven) | Controllers HTTP (driving), repositories Doctrine, envoi d'emails, security, commandes CLI, event subscribers |

### Patterns clés

- **Value Objects** - Types immuables auto-validants (`Email`, `Phone`, `Address`, `TimeSlot`...). Les enums PHP (`AppointmentStatus`, `AppointmentModality`, `UserRole`, `WeekDay`) portent la logique métier (`canTransitionTo()`, `blocksSlot()`, `isTerminal()`).
- **DTOs Input/Output** - `final readonly class` avec factory `fromEntity()` et `toArray()`. Séparation nette entre ce qui entre dans un handler et ce qui en sort.
- **Repository Pattern** - Interfaces définies dans le Domain (ports), implémentées dans l'Infrastructure avec Doctrine (adapters).
- **Custom DBAL Types** - Types Doctrine personnalisés (`EmailType`, `UserIdType`, `HashedStringType`...) pour convertir automatiquement entre Value Objects PHP et colonnes de base de données. Les entités Domain portent directement les attributs `#[ORM\Entity]` et `#[ORM\Column]` - pas de couche ORM séparée.
- **Reconstitution Pattern** - Méthodes statiques `reconstitute()` sur les entités Domain pour créer des objets dans un état spécifique sans déclencher de logique métier. Utilisé uniquement par les helpers de test (`DomainTestHelper`, tests unitaires et d'intégration).
- **Parameter Objects** - `AvailabilityContext` regroupe schedules, exceptions, appointments et locks pour éviter l'explosion de paramètres.

---

## Frontends

Le projet comporte deux applications frontend distinctes :

**Landing** (`landing/`) - Astro + Svelte (Islands Architecture)

Site public destiné aux visiteurs. Les pages sont générées en HTML statique au build, et seuls les composants interactifs (flux de prise de rendez-vous) sont hydratés côté client avec Svelte.

**Dashboard** (`dashboard/`) - Angular 21 + Angular Material

Portail privé pour la thérapeute et les patients. Application SPA avec navigation par rôle, formulaires réactifs, et composants Material Design. Gère aujourd'hui l'authentification, les patients et les invitations. Les écrans planning et rendez-vous sont des routes vides en attente d'implémentation.

---

## Stack technique

| Couche | Technologies |
| ------ | ------------ |
| Backend | PHP 8.4 (`strict_types` obligatoire), Symfony 8.0, Doctrine ORM 3.0 |
| Frontend Landing | Astro 5.7, Svelte 5, Tailwind CSS 3.4 |
| Frontend Dashboard | Angular 21, Angular Material 21, TypeScript, RxJS |
| Base de données | PostgreSQL 16, clés primaires UUID, index composites pour les requêtes de disponibilité |
| Cache / Sessions | Redis 7 - blocklist JWT (`jti`), expiration automatique |
| Authentification | JWT avec révocation par claim `jti` via Redis. Cookie httpOnly unique `THERAPY_JWT` (une session par navigateur ; un login remplace le cookie, le logout le supprime et révoque le `jti`). Bearer token pour les clients API. |
| Emails | Symfony Mailer - MailHog en dev, SMTP en prod |
| Infrastructure | Docker Compose (9 conteneurs par défaut + 3 conteneurs Playwright sous le profil `e2e` : e2e dashboard, e2e landing, serveur de rapport HTML), cron planifié, Makefile |

---

## Tests

Tests répartis en deux suites :

| Suite | Portée | Base de données |
| ----- | ------ | --------------- |
| **Unit** | Entités, Value Objects, Handlers (avec mocks) | Non |
| **Integration** | Repositories Doctrine, Controllers HTTP (requêtes réelles) | Oui (PostgreSQL test) |

### Patterns de test

- **Isolation transactionnelle** - Chaque test d'intégration s'exécute dans une transaction qui est rollback automatiquement dans `tearDown()`. Aucune donnée ne persiste entre les tests.
- **DomainTestHelper** - Factory methods pour créer des objets domain dans n'importe quel état (utilisateurs actifs/inactifs, tokens valides/expirés/utilisés).
- **ApiTestCase** - Classe de base avec client HTTP, helpers d'authentification (`createTherapistAndGetToken()`), et wrapping transactionnel.
- **Kernel reboot disabled** - `$this->client->disableReboot()` maintient le même kernel Symfony entre plusieurs requêtes HTTP, garantissant que l'EntityManager voit les données non commitées de la transaction de test.

```bash
# Tous les tests
docker-compose exec php vendor/bin/phpunit

# Unit uniquement (rapide, sans BDD)
docker-compose exec php vendor/bin/phpunit --testsuite=Unit

# Integration uniquement
docker-compose exec php vendor/bin/phpunit --testsuite=Integration
```

### Tests E2E (Playwright)

Suite end-to-end qui pilote un vrai Chromium contre le dashboard en cours d'exécution. Exécutée dans un conteneur Docker dédié (`mcr.microsoft.com/playwright:v1.49.1-noble`), aucune installation sur l'hôte.

- Couvre : invitation patient (happy path), authentification (login, logout, route guards, réinitialisation de mot de passe), resend + revoke, et 4 chemins d'erreur (token utilisé, token invalide, email invalide, mots de passe non-correspondants).
- `globalSetup` se connecte une fois en tant que thérapeute et persiste la session via `storageState` - réutilisée par tous les tests pour éviter de saturer le rate limiter (5 logins/min/IP).
- Exécutée aussi en CI (job `e2e`), qui démarre la stack via `docker-compose.ci.yml`. Une suite e2e distincte couvre le landing (`landing/e2e/`, service `playwright-landing`).
- Le job `e2e` est indicatif : il ne bloque pas la fusion, seul le job `test` est requis. Voir [`docs/STATUS.md`](docs/STATUS.md) avant de supposer qu'un `e2e` rouge vient de vos changements.

```bash
# Lancer la suite E2E complète
docker-compose --profile e2e run --rm playwright

# Lancer un fichier spécifique
docker-compose --profile e2e run --rm playwright \
  npx playwright test invitation-happy-path
```

Pour visualiser le rapport après une exécution :

```bash
docker-compose --profile e2e up playwright-report
# puis ouvrir http://localhost:9323 (Ctrl-C pour arrêter)
```

Le serveur dédié est nécessaire car la visionneuse de traces ne fonctionne pas en `file://`. Détails dans le README e2e.

Documentation détaillée : [`dashboard/e2e/README.md`](dashboard/e2e/README.md).

---

## Commandes utiles

```bash
# Créer le compte thérapeute (un seul autorisé)
# Le mot de passe doit contenir majuscule, minuscule, chiffre et caractère spécial (8-72 caractères)
docker-compose exec php php bin/console app:create-therapist "email@example.com" "Dr. Nom" "MotDePasse1!"

# Nettoyer les tokens expirés (invitations + reset password)
docker-compose exec php php bin/console app:cleanup-tokens

# Envoyer l'agenda quotidien manuellement (normalement déclenché par cron)
docker-compose exec php php bin/console app:send-daily-agenda

# Seed de créneaux d'exemple pour le développement
docker-compose exec php php bin/console app:seed-schedule

# Supprimer les verrous de créneaux expirés (normalement déclenché par cron)
docker-compose exec php php bin/console app:cleanup-slot-locks

# Vider la boîte MailHog
curl -X DELETE http://localhost:8025/api/v1/messages

# Vider le cache Symfony (après modif config)
docker-compose exec php php bin/console cache:clear --env=dev
docker-compose exec php php bin/console cache:clear --env=test
```

---

## Aperçu API

Endpoints REST organisés par domaine, avec un format de réponse uniforme :

```json
{
  "success": true,
  "data": { "..." },
  "pagination": { "page": 1, "limit": 20, "total": 42, "total_pages": 3 }
}
```

Les endpoints couvrent : authentification, gestion des patients, planning, disponibilités, rendez-vous, et suivi du règlement (marquage manuel - les paiements sont réglés hors application).

Une **collection Postman** complète est incluse dans [`API/postman/`](API/postman/) avec variables pré-configurées et scripts de test.

Pour la référence complète des endpoints, voir le [README de l'API](API/README.md#api-endpoints).

---

## Démarrage rapide

**Prérequis** : Docker Desktop

```bash
git clone <repo-url> therapy && cd therapy

# Variables d'environnement (le .env est gitignoré)
cp API/.env.example API/.env

docker-compose up -d --build

# Générer la paire de clés JWT (les .pem sont gitignorés)
docker-compose exec php php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction

docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker-compose exec php php bin/console app:create-therapist "email@example.com" "Dr. Nom" "MotDePasse1!"
```

| Service | URL |
| ------- | --- |
| API | <http://localhost:8080/api> |
| Landing | <http://localhost:4321> |
| Dashboard | <http://localhost:4200> |
| MailHog (emails) | <http://localhost:8025> |
| pgAdmin | <http://localhost:5050> |

Pour le setup complet (BDD test, JWT, troubleshooting), voir le [README de l'API](API/README.md).

---

## Structure du projet

```
therapy/
├── API/                          # Backend Symfony 8.0
│   ├── src/
│   │   ├── Domain/               # Logique métier pure (entités, value objects, ports)
│   │   ├── Application/          # Cas d'usage (handlers, DTOs)
│   │   └── Infrastructure/       # Adaptateurs (Doctrine, HTTP, email, CLI)
│   ├── tests/
│   │   ├── Unit/                 # Tests unitaires (domain + handlers)
│   │   └── Integration/         # Tests d'intégration (repos + controllers)
│   ├── config/                   # Configuration Symfony
│   ├── migrations/               # Migrations Doctrine
│   └── postman/                  # Collection Postman
│
├── landing/                      # Site public Astro + Svelte
│   ├── src/
│   │   ├── pages/                # Routes Astro (index.astro)
│   │   ├── layouts/              # Layout de base partagé
│   │   ├── components/
│   │   │   ├── astro/            # Composants serveur (layout, bio, services)
│   │   │   └── svelte/           # Composants client (flux rendez-vous)
│   │   ├── services/             # Client API typé
│   │   ├── types/                # Interfaces TypeScript
│   │   ├── utils/                # Utilitaires date/heure
│   │   └── content/              # Content Collections (markdown)
│   └── public/                   # Assets statiques
│
├── dashboard/                    # Portail Angular (thérapeute + patients)
│   ├── src/app/
│   │   ├── auth/                 # Login, registration, reset password
│   │   ├── layout/               # Navigation et structure par rôle
│   │   ├── appointments/         # Gestion des rendez-vous (route vide pour l'instant)
│   │   ├── patients/             # Gestion des patients
│   │   ├── schedule/             # Planning et disponibilités (route vide pour l'instant)
│   │   └── shared/               # Services, guards, interceptors
│   └── e2e/                      # Tests Playwright (config + fixtures + specs)
│
├── docker-compose.yml            # 9 services (PHP, Nginx, PostgreSQL, Redis, MailHog, pgAdmin, cron, landing, dashboard) + 3 services Playwright sous le profil e2e (playwright = e2e dashboard, playwright-landing = e2e landing, playwright-report = serveur HTML du rapport)
└── Makefile                      # Commandes raccourcies
```

---

## Documentation

| Fichier | Contenu |
| ------- | ------- |
| [`docs/STATUS.md`](docs/STATUS.md) | État d'avancement par composant. La référence pour savoir ce qui est fait. |
| [`docs/adr/`](docs/adr/) | Décisions d'architecture (stockage UTC, ancrage des récurrences, tests, jobs planifiés). |
| [`API/docs/database-schema.md`](API/docs/database-schema.md) | Schéma de base : tables, colonnes, index, contraintes. |
| [`API/README.md`](API/README.md) | Référence complète de l'API : endpoints, architecture, tests. |
| [`CONTEXT.md`](CONTEXT.md) | Glossaire du domaine. À lire avant de nommer quoi que ce soit. |
