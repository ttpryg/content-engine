-- ContentEngine Database Schema
-- Standard MySQL / MariaDB DDL with Polymorphic Multi-Tenant support

CREATE TABLE IF NOT EXISTS contents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_type VARCHAR(50) NULL COMMENT 'Tipe tenant, misal: store, company, blog, platform (null = global)',
    tenant_id VARCHAR(100) NULL COMMENT 'ID tenant (null = global)',
    author_id BIGINT UNSIGNED NULL COMMENT 'ID User pembuat/penulis dari auth-user',
    type VARCHAR(30) NOT NULL COMMENT 'page, post, testimonial, faq',
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NULL,
    summary VARCHAR(500) NULL COMMENT 'Excerpt/ringkasan konten',
    body LONGTEXT NULL,
    meta JSON NULL COMMENT 'Fleksibel: testimonial rating, perusahaan, SEO metadata',
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
    INDEX idx_slug_type (slug, type),
    INDEX idx_sort (type, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    type VARCHAR(30) NOT NULL DEFAULT 'category' COMMENT 'category, tag, group',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_category (
    content_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (content_id, category_id),
    CONSTRAINT fk_content_category_content FOREIGN KEY (content_id) REFERENCES contents(id) ON DELETE CASCADE,
    CONSTRAINT fk_content_category_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
