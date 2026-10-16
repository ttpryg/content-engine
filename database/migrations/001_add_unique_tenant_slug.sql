-- 001: Tambahkan UNIQUE KEY multi-tenant pada tabel contents.
--
-- Tujuan: mencegah tabrakan slug antar tenant (tenant_type, tenant_id) per type.
-- Jalankan hanya pada database yang dibuat sebelum fitur unique key diimplementasikan.
-- Fresh install via DatabaseMigrator::migrate() sudah mencakup constraint ini di schema.sql
-- dan menandai migrasi ini sebagai baseline secara otomatis.
ALTER TABLE contents ADD UNIQUE KEY uk_tenant_slug (tenant_type, tenant_id, type, slug);