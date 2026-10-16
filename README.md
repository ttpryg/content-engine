# ContentEngine Library

`ttpryg/content-engine` is a framework-agnostic standalone PHP library for CMS content management (posts, pages, testimonials, FAQs), taxonomy (categories/tags), polymorphic multi-tenant scoping (`tenant_type`, `tenant_id`), automatic slug generation, metadata wrapping, and status lifecycle management.

## 🌟 Key Features

- **Framework Agnostic**: Compatible with any PHP 8.1+ project (Vanilla PHP, Slim, Laravel, Symfony, CodeIgniter).
- **Polymorphic Multi-Tenant Scoping**:
  - `tenant_type` & `tenant_id`: Generic scoping for stores (`store`), companies (`company`), blogs (`blog`), or organizations (`findByTenant`).
  - `author_id`: Track author/user ownership from `auth-user` (`findByAuthorId`).
  - `null` scoping: Global platform content managed by system admins (e.g. Terms of Service, Main Platform Blog).
- **Multi-Content Support**: Standardized handling for `post`, `page`, `testimonial`, `faq`, or custom content types.
- **Improved Database Schema**:
  - `contents` table with `tenant_type`, `tenant_id`, `author_id`, `summary` (excerpt), `sort_order`, `view_count`, `deleted_at` (soft deletes), and `meta` (JSON).
  - `categories` & `content_category` tables for Taxonomy (Categories & Tags).
- **Strongly Typed Meta Wrappers**:
  - `TestimonialMeta`: Company, Position, Rating, Avatar URL.
  - `SeoMeta`: Meta Title, Meta Description, OG Image, Keywords.
- **Automatic Slug Generator**: Converts titles to clean URLs with collision resolution (e.g. `my-post-1`, `my-post-2`), including soft-deleted entries so `restore()` never hits a unique-key collision.
- **Domain Events**: `ContentCreatedEvent`, `ContentPublishedEvent`, `ContentArchivedEvent`, `ContentDeletedEvent`, `ContentViewedEvent`.
- **Security Hardening**: `ORDER BY` column whitelist (unknown columns throw `InvalidOrderByException`), pagination clamped to 1–100 rows (DoS prevention), and parameterized PDO queries throughout.
- **Versioned Schema Migrations**: `MigrationRunner` with a `content_engine_migrations` tracker table — incremental SQL migrations run once, are recorded only after success, and resume safely after failures.
- **Scalability Options**: `deferViewCounts: true` skips the per-read `UPDATE view_count` (avoids row-lock contention under heavy traffic); `ContentViewedEvent` provides the hook for async buffering (Redis / queue).

---

## 🗄️ Database Schema

Run the SQL script from `database/schema.sql` (fresh install) or use `DatabaseMigrator` (see [Database Migrations](#-database-migrations)). The script also creates the `categories` and `content_category` (pivot) tables:

```sql
CREATE TABLE IF NOT EXISTS contents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_type VARCHAR(50) NULL COMMENT 'Tipe tenant (store, company, blog)',
    tenant_id VARCHAR(100) NULL COMMENT 'ID tenant',
    author_id BIGINT UNSIGNED NULL COMMENT 'ID User (AuthUser)',
    type VARCHAR(30) NOT NULL COMMENT 'page, post, testimonial, faq',
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NULL,
    summary VARCHAR(500) NULL,
    body LONGTEXT NULL,
    meta JSON NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    sort_order INT NOT NULL DEFAULT 0,
    view_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uk_tenant_slug (tenant_type, tenant_id, type, slug),
    INDEX idx_tenant_content (tenant_type, tenant_id, type, status),
    INDEX idx_author_content (author_id, status),
    INDEX idx_type_status_published (type, status, published_at),
    INDEX idx_slug_type (slug, type),
    INDEX idx_sort (type, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> The `UNIQUE KEY uk_tenant_slug` prevents slug collisions across tenants per content type, and doubles as the composite index for `(tenant_type, tenant_id, type, slug)` lookups.

---

## 🚀 Quick Usage Example

```php
use PDO;
use Ttpryg\ContentEngine\Repositories\PdoContentRepository;
use Ttpryg\ContentEngine\Services\ContentService;

// 1. Initialize PDO & Services
$pdo = new PDO("mysql:host=localhost;dbname=my_db", "root", "secret");
$contentRepo = new PdoContentRepository($pdo);
$contentService = new ContentService($contentRepo);

// 2. Create Global Platform Announcement (Admin)
$globalPost = $contentService->createContent(
    title: 'Platform Maintenance Schedule',
    type: 'post',
    body: 'System will be updated on Sunday...',
    status: 'published'
);

// 3. Create Store-Specific News (Owner of Store 101)
$storePost = $contentService->createContent(
    title: 'Grand Opening Sale at Toko Sepatu Jaya!',
    type: 'post',
    body: 'Visit our store for 50% off...',
    status: 'published',
    authorId: 77,         // User ID from auth-user
    tenantType: 'store',  // Tenant Scope
    tenantId: '101'       // Store ID
);

// 4. Query Posts for Store 101
$storePosts = $contentRepo->findByTenant(tenantType: 'store', tenantId: '101', type: 'post');
```

---

## 🔄 Database Migrations

**Fresh install** — bootstraps the full schema from `database/schema.sql` and records every bundled migration as a baseline (so they are never re-executed):

```php
use Ttpryg\ContentEngine\Database\DatabaseMigrator;

(new DatabaseMigrator($pdo))->migrate();
```

**Existing database** (e.g. created before a newer schema change) — run pending migrations from `database/migrations/*.sql`. Each file executes once in name order, is recorded in the `content_engine_migrations` tracker table **only after success**, and is automatically retried on the next run if it fails:

```php
use Ttpryg\ContentEngine\Database\MigrationRunner;

$runner = new MigrationRunner($pdo);
$runner->migrate();            // e.g. ['001_add_unique_tenant_slug']
$runner->appliedMigrations();  // full applied history
```

> Migration `001_add_unique_tenant_slug` upgrades databases created before the multi-tenant slug unique key existed. Fresh installs skip it automatically (baseline).

---

## ⚡ High-Traffic View Counting

By default, every read via `getContentBySlug()` runs `UPDATE contents SET view_count = view_count + 1`, taking a row lock per read. Under heavy traffic, enable deferred counting:

```php
use Ttpryg\ContentEngine\Services\ContentService;

$contentService = new ContentService(
    contentRepository: $contentRepo,
    eventDispatcher: $dispatcher,
    deferViewCounts: true   // no UPDATE on read
);
```

`ContentViewedEvent` is still dispatched on every read — subscribe a listener that buffers counts (e.g. in Redis or `_modules/queue`) and flushes them in batches. The default (`deferViewCounts: false`) preserves the original behaviour.

---

## 🛠️ Development

```bash
composer test      # PHPUnit (unit + integration, in-memory SQLite)
composer analyse   # PHPStan level 5 (src + tests)
composer fix       # Rector + Pint (auto-fix)
composer fix:check # Rector dry-run + Pint test + PHPStan (CI gate)
```

---

## 📄 License
MIT License.
