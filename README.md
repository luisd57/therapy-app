# Therapy — Gestion de cabinet de psychothérapie

![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Symfony 8.0](https://img.shields.io/badge/Symfony-8.0-000000?logo=symfony&logoColor=white)
![PostgreSQL 16](https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white)
![Redis 7](https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white)
![Astro 5](https://img.shields.io/badge/Astro-5.7-BC52EE?logo=astro&logoColor=white)
![Svelte 5](https://img.shields.io/badge/Svelte-5-FF3E00?logo=svelte&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.4-06B6D4?logo=tailwindcss&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)

Application web (en développement) pour la gestion d'un cabinet de psychothérapie individuel. Les visiteurs consultent les disponibilités et soumettent des demandes de rendez-vous. La thérapeute gère son planning, confirme ou annule les rendez-vous, et intègre ses patients via un système d'invitation.

Projet conçu après d'un cas réel : Cabinet Thérapeutique avec un besoin de solution logiciel.

---

## Fonctionnalités

**Côté visiteur / patient**

- Consultation des créneaux disponibles en temps réel, filtrables par modalité (en ligne / en personne)
- Formulaire de prise de rendez-vous avec email de confirmation automatique
- Inscription patient sur invitation uniquement (lien signé, durée limitée)
- Réinitialisation de mot de passe par email

**Côté thérapeute**

- Planning hebdomadaire récurrent avec créneaux configurables par jour et par modalité
- Exceptions de planning (vacances, indisponibilités ponctuelles)
- Cycle de vie complet des rendez-vous : `REQUESTED → CONFIRMED → COMPLETED / CANCELLED`
- Création manuelle de rendez-vous pour les patients existants
- Email d'agenda quotidien (envoyé par cron)
- Gestion des patients et des invitations en cours

---

## Architecture Backend — Hexagonale (Ports & Adapters)

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
| **Application** | Orchestration des cas d'usage | 33 Handlers (1 fichier = 1 use case = 1 méthode `__invoke()`), DTOs Input/Output |
| **Infrastructure** | Adaptateurs techniques (driving + driven) | Controllers HTTP (driving), repositories Doctrine, envoi d'emails, security, commandes CLI, event subscribers |

### Patterns clés

- **Value Objects** — Types immuables auto-validants (`Email`, `Phone`, `Address`, `TimeSlot`, `AppointmentStatus`…). Les enums PHP portent la logique métier (`canTransitionTo()`, `blocksSlot()`, `isTerminal()`).
- **DTOs Input/Output** — `final readonly class` avec factory `fromEntity()` et `toArray()`. Séparation nette entre ce qui entre dans un handler et ce qui en sort.
- **Repository Pattern** — Interfaces définies dans le Domain (ports), implémentées dans l'Infrastructure avec Doctrine (adapters). Aucune clé étrangère physique en base — l'intégrité référentielle est assurée par le domaine.
- **Mappers bidirectionnels** — `toDomain()` / `toEntity()` pour convertir entre entités Doctrine et entités Domain, avec pattern upsert.
- **Reconstitution Pattern** — Méthodes statiques `reconstitute()` sur les entités Domain pour l'hydratation depuis la base sans déclencher de logique métier. Utilisé uniquement par les repositories et les helpers de test.
- **Parameter Objects** — `AvailabilityContext` regroupe schedules, exceptions, appointments et locks pour éviter l'explosion de paramètres.

---

## Frontend — Astro + Svelte (Islands Architecture)

Le frontend utilise l'architecture en îlots d'Astro : les pages sont générées en HTML statique au build, et seuls les composants interactifs sont hydratés côté client avec Svelte.

---

## Stack technique

| Couche | Technologies |
| ------ | ------------ |
| Backend | PHP 8.4 (`strict_types` obligatoire), Symfony 8.0, Doctrine ORM 3.0 |
| Frontend | Astro 5.7, Svelte 5, Tailwind CSS 3.4 |
| Base de données | PostgreSQL 16, clés primaires UUID, index optimisés pour les requêtes de disponibilité |
| Cache / Messaging | Redis 7 — blocklist JWT (`jti`), expiration automatique |
| Authentification | JWT stateless avec révocation par claim `jti` via Redis |
| Emails | Symfony Mailer — MailHog en dev, SMTP en prod |
| Infrastructure | Docker Compose (8 conteneurs), cron planifié, Makefile |

---

## Tests

Tests répartis en deux suites :

| Suite | Portée | Base de données |
| ----- | ------ | --------------- |
| **Unit** | Entités, Value Objects, Handlers (avec mocks) | Non |
| **Integration** | Repositories Doctrine, Controllers HTTP (requêtes réelles) | Oui (PostgreSQL test) |

### Patterns de test

- **Isolation transactionnelle** — Chaque test d'intégration s'exécute dans une transaction qui est rollback automatiquement dans `tearDown()`. Aucune donnée ne persiste entre les tests.
- **DomainTestHelper** — Factory methods pour créer des objets domain dans n'importe quel état (utilisateurs actifs/inactifs, tokens valides/expirés/utilisés).
- **ApiTestCase** — Classe de base avec client HTTP, helpers d'authentification (`createTherapistAndGetToken()`), et wrapping transactionnel.
- **Kernel reboot disabled** — `$this->client->disableReboot()` maintient le même kernel Symfony entre plusieurs requêtes HTTP, garantissant que l'EntityManager voit les données non commitées de la transaction de test.

```bash
# Tous les tests
docker-compose exec php vendor/bin/phpunit

# Unit uniquement (rapide, sans BDD)
docker-compose exec php vendor/bin/phpunit --testsuite=Unit

# Integration uniquement
docker-compose exec php vendor/bin/phpunit --testsuite=Integration
```

---

## Aperçu API

40+ endpoints REST organisés par domaine, avec un format de réponse uniforme :

```json
{
  "success": true,
  "data": { "..." },
  "pagination": { "page": 1, "limit": 20, "total": 42, "total_pages": 3 }
}
```

Les endpoints couvrent : authentification, gestion des patients, planning, disponibilités, rendez-vous, et paiements.

Une **collection Postman** complète est incluse dans [`api/postman/`](api/postman/) avec variables pré-configurées et scripts de test.

Pour la référence complète des endpoints, voir le [README de l'API](api/README.md#api-endpoints).

---

## Démarrage rapide

**Prérequis** : Docker Desktop

```bash
git clone <repo-url> && cd therapy
docker-compose up -d --build
docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker-compose exec php php bin/console app:create-therapist "email@example.com" "Dr. Nom" "motdepasse"
```

| Service | URL |
| ------- | --- |
| API | <http://localhost:8080/api/health> |
| Frontend | <http://localhost:4321> |
| MailHog (emails) | <http://localhost:8025> |

Pour le setup complet (BDD test, JWT, troubleshooting), voir le [README de l'API](api/README.md).

---

## Structure du projet

```
therapy/
├── api/                          # Backend Symfony 8.0
│   ├── src/
│   │   ├── Domain/               # Logique métier pure (entités, value objects, ports)
│   │   ├── Application/          # Cas d'usage (32 handlers, DTOs)
│   │   └── Infrastructure/       # Adaptateurs (Doctrine, HTTP, email, CLI)
│   ├── tests/
│   │   ├── Unit/                 # Tests unitaires (domain + handlers)
│   │   └── Integration/         # Tests d'intégration (repos + controllers)
│   ├── config/                   # Configuration Symfony
│   ├── migrations/               # Migrations Doctrine
│   └── postman/                  # Collection Postman
│
├── ui/                           # Frontend Astro + Svelte
│   ├── src/
│   │   ├── pages/                # Routes Astro (index.astro)
│   │   ├── components/
│   │   │   ├── astro/            # Composants serveur (layout, bio, services)
│   │   │   └── svelte/           # Composants client (flux rendez-vous)
│   │   ├── services/             # Client API typé
│   │   ├── types/                # Interfaces TypeScript
│   │   ├── utils/                # Utilitaires date/heure
│   │   └── content/              # Content Collections (markdown)
│   └── public/                   # Assets statiques
│
├── docker-compose.yml            # 8 conteneurs (PHP, Nginx, PostgreSQL, Redis, MailHog, pgAdmin, cron, Astro)
└── Makefile                      # Commandes raccourcies
```
