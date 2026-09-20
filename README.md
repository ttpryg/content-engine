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
- **Automatic Slug Generator**: Converts titles to clean URLs with collision resolution (e.g. `my-post-1`, `my-post-2`).
- **Domain Events**: `ContentCreatedEvent`, `ContentPublishedEvent`, `ContentArchivedEvent`, `ContentDeletedEvent`.

---

## 🗄️ Database Schema

Run the SQL script from `database/schema.sql` or use `DatabaseMigrator`:

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
    INDEX idx_tenant_content (tenant_type, tenant_id, type, status),
    INDEX idx_author_content (author_id, status),
    INDEX idx_type_status_published (type, status, published_at),
    INDEX idx_slug_type (slug, type)
);
```

---

## 🚀 Quick Usage Example

```php
use PDO;
use Ttpryg\ContentEngine\Repositories\PdoContentRepository;
use Ttpryg\ContentEngine\Services\ContentService;
use Ttpryg\ContentEngine\ValueObjects\TestimonialMeta;

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

## 📄 License
MIT License.
