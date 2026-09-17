# ContentEngine Library

`ttpryg/content-engine` is a framework-agnostic standalone PHP library for CMS content management (posts, pages, testimonials, FAQs), taxonomy (categories/tags), automatic slug generation, metadata wrapping, and status lifecycle management.

## 🌟 Key Features

- **Framework Agnostic**: Compatible with any PHP 8.1+ project (Vanilla PHP, Slim, Laravel, Symfony, CodeIgniter).
- **Multi-Content Support**: Standardized handling for `post`, `page`, `testimonial`, `faq`, or custom content types.
- **Improved Database Schema**:
  - `contents` table with `summary` (excerpt), `sort_order`, `view_count`, `deleted_at` (soft deletes), and `meta` (JSON).
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
    type VARCHAR(30) NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NULL,
    summary VARCHAR(500) NULL,
    body LONGTEXT NULL,
    meta JSON NULL,
    status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft',
    sort_order INT NOT NULL DEFAULT 0,
    view_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    author_id BIGINT UNSIGNED NULL,
    published_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_type_status_published (type, status, published_at),
    INDEX idx_slug_type (slug, type),
    INDEX idx_sort (type, sort_order)
);
```

---

## 🚀 Quick Usage Example

```php
use PDO;
use Ttpryg\ContentEngine\Repositories\PdoContentRepository;
use Ttpryg\ContentEngine\Services\ContentService;
use Ttpryg\ContentEngine\ValueObjects\TestimonialMeta;

// 1. Initialize PDO
$pdo = new PDO("mysql:host=localhost;dbname=my_db", "root", "secret");

// 2. Setup Service
$contentRepo = new PdoContentRepository($pdo);
$contentService = new ContentService($contentRepo);

// 3. Create a Blog Post
$post = $contentService->createContent(
    title: 'Getting Started with Content Engine',
    type: 'post',
    summary: 'A complete guide to CMS content management.',
    body: '<p>Full HTML content goes here...</p>',
    status: 'published'
);

// 4. Create a Testimonial with Typed Meta
$meta = new TestimonialMeta(company: 'Google', position: 'Tech Lead', rating: 5);
$testimonial = $contentService->createContent(
    title: 'John Doe',
    type: 'testimonial',
    body: 'Awesome CMS library!',
    meta: $meta->toArray(),
    status: 'published'
);

// 5. Query Published Posts
$publishedPosts = $contentRepo->findAll(['type' => 'post', 'status' => 'published']);
```

---

## 📄 License
MIT License.
