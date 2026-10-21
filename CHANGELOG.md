# Changelog

All notable changes to `ttpryg/content-engine` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- In-memory repository implementations (`InMemoryContentRepository`,
  `InMemoryCategoryRepository`) for fast, database-free fakes in tests and prototypes.

### Changed

- **Breaking:** separated the domain and application layers. Entities, value objects,
  enums, events, repository interfaces, and exceptions now live under
  `Ttpryg\ContentEngine\Domain\*`, while application services moved to
  `Ttpryg\ContentEngine\Application\Service\*`. Update imports accordingly.
- **Breaking:** PDO persistence adapters moved out of the module core from
  `Ttpryg\ContentEngine\Repositories` to
  `Ttpryg\ContentEngine\Infrastructure\Persistence\Pdo`. The module core now depends
  only on repository ports; register the `Infrastructure` PSR-4 namespace when using
  the bundled adapters.
- **Breaking:** replaced the custom migration runner with
  [`ttpryg/database-migrator`](https://github.com/ttpryg/database-migrator). Migrations
  are now dated PHP classes with `up()` and `down()` support; the entry point moved to
  `Ttpryg\ContentEngine\Infrastructure\Database\DatabaseMigrator`. The legacy custom
  runner and `database/schema.sql` were removed. Existing deployments tracked by the
  legacy `001_add_unique_tenant_slug` name remain compatible.

## [1.3.0] - 2026-10-18

### Added

- Root `ExceptionInterface` marker interface (`Ttpryg\ContentEngine\Exceptions\ExceptionInterface`)
  that extends `Throwable` and is implemented by `ContentEngineException` (and, through
  inheritance, all specific content exceptions), so consumers can catch all
  library-specific errors with a single type.
- `.editorconfig` for consistent editor settings across the project.

### Changed

- Added `declare(strict_types=1)` to all source files that were missing it.

## [1.2.0] - 2026-10-16

### Added

- Versioned migration system (`DatabaseMigrator` + `MigrationRunner`) with a baseline
  migration that enforces a unique `(tenant, slug)` constraint on content.
- `InvalidOrderByException` and order-by validation for content listings.
- `ContentViewedEvent` dispatching for content views.
- Category repository tests and a cross-database compatible `attachToContent()`.

### Changed

- Prevented slug collisions when generating content slugs.
- Made `DateTimeInterface` entity properties non-nullable.

### Removed

- Removed the Nginx service from `docker-compose.yml` and deleted the default Nginx configuration.

## [1.1.2] - 2026-09-21

### Changed

- Added the event dispatcher dependency and updated the event-dispatcher interface implementation.

## [1.1.1] - 2026-09-20

### Changed

- Updated the Composer configuration and added a Rector setup for code quality.

## [1.1.0] - 2026-09-20

### Added

- Polymorphic multi-tenant support across content and categories.

## [1.0.0] - 2026-09-17

### Added

- Initial release: `ContentService` and `CategoryService` with content types
  (article/page/testimonial), SEO and testimonial metadata value objects, and content status.
- Domain events: `ContentCreatedEvent`, `ContentPublishedEvent`, `ContentArchivedEvent`,
  `ContentDeletedEvent`.
- PDO repositories and a native slug generator.

[Unreleased]: https://github.com/ttpryg/content-engine/compare/1.2.0...HEAD
[1.2.0]: https://github.com/ttpryg/content-engine/compare/1.1.2...1.2.0
[1.1.2]: https://github.com/ttpryg/content-engine/compare/1.1.1...1.1.2
[1.1.1]: https://github.com/ttpryg/content-engine/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/ttpryg/content-engine/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/ttpryg/content-engine/releases/tag/1.0.0
