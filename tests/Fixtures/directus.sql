-- A small Directus, with the tables and columns of the real one that the copy
-- reads: three accounts (one without email), four files (one on S3, one not used), three people, two
-- organizations, three categories (one with its parent after it), four
-- documents (one without its file), a utility with its documents and two
-- reminders. Loaded by tests/Feature/Copy/CopyFromDirectusTest.php.

CREATE TABLE directus_users (
    id char(36) NOT NULL PRIMARY KEY,
    first_name varchar(50) NULL,
    last_name varchar(50) NULL,
    email varchar(128) NULL,
    password varchar(255) NULL,
    status varchar(16) NOT NULL DEFAULT 'active'
);
INSERT INTO directus_users (id, first_name, last_name, email, password, status) VALUES
    ('11111111-1111-1111-1111-111111111111', 'Laura', 'Verdi', 'laura.verdi@example.com', '$argon2id$not-copied', 'active'),
    ('22222222-2222-2222-2222-222222222222', 'Admin', 'Directus', 'admin@example.com', '$argon2id$not-copied', 'active'),
    ('33333333-3333-3333-3333-333333333333', 'Paolo', NULL, NULL, NULL, 'active');

CREATE TABLE directus_files (
    id char(36) NOT NULL PRIMARY KEY,
    storage varchar(255) NOT NULL DEFAULT 'local',
    filename_disk varchar(255) NULL,
    filename_download varchar(255) NOT NULL,
    type varchar(255) NULL,
    folder char(36) NULL,
    filesize bigint NULL,
    uploaded_on timestamp NULL,
    reading json NULL
);
INSERT INTO directus_files (id, storage, filename_disk, filename_download, type, filesize, uploaded_on, reading) VALUES
    ('aaaaaaaa-0000-0000-0000-000000000001', 'local', 'aaaaaaaa-0000-0000-0000-000000000001.pdf', 'bolletta-ottobre.pdf', 'application/pdf', 12, '2026-10-01 09:00:00', '{"model": "x"}'),
    ('aaaaaaaa-0000-0000-0000-000000000002', 'local', 'aaaaaaaa-0000-0000-0000-000000000002.pdf', 'contratto-luce.pdf', 'application/pdf', 34, '2026-09-01 09:00:00', NULL),
    ('aaaaaaaa-0000-0000-0000-000000000003', 's3', 'aaaaaaaa-0000-0000-0000-000000000003.jpg', 'tessera.jpg', 'image/jpeg', 56, '2026-08-01 09:00:00', NULL),
    ('aaaaaaaa-0000-0000-0000-000000000004', 'local', 'aaaaaaaa-0000-0000-0000-000000000004.jpg', 'non-usato.jpg', 'image/jpeg', 78, '2026-07-01 09:00:00', NULL);

CREATE TABLE people (
    id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    first_name varchar(255) NULL,
    last_name varchar(255) NULL,
    initials varchar(255) NULL,
    role varchar(255) NULL,
    birth_date date NULL,
    tax_code varchar(255) NULL,
    photo char(36) NULL,
    user char(36) NULL,
    notes text NULL,
    upload_token varchar(255) NULL
);
INSERT INTO people (id, first_name, last_name, initials, role, birth_date, user, upload_token) VALUES
    (3, 'Laura', 'Verdi', 'LV', 'parent', '1984-02-11', '11111111-1111-1111-1111-111111111111', 'secret-link'),
    (5, 'Paolo', 'Verdi', 'PV', 'parent', '1982-07-30', '33333333-3333-3333-3333-333333333333', NULL),
    (8, 'Sara', 'Verdi', 'SV', 'child', '2015-04-02', NULL, NULL);

CREATE TABLE organizations (
    id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name varchar(255) NULL,
    types json NULL,
    email varchar(255) NULL,
    phone varchar(255) NULL,
    website varchar(255) NULL,
    icon varchar(255) NULL,
    logo char(36) NULL,
    notes text NULL
);
INSERT INTO organizations (id, name, types, phone, website) VALUES
    (7, 'Edison', '["utility_provider"]', '800 031 141', 'edison.it'),
    (9, 'Comune di Bologna', '["public_office"]', NULL, NULL);

CREATE TABLE document_categories (
    id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name varchar(255) NULL,
    parent int unsigned NULL,
    icon varchar(255) NULL,
    color varchar(255) NULL,
    sort int NULL,
    folder char(36) NULL
);
INSERT INTO document_categories (id, name, parent, sort) VALUES
    (1, 'Bollette', 2, 1),
    (2, 'Casa', NULL, 1),
    (4, 'Salute', NULL, 2);

CREATE TABLE documents (
    id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title varchar(255) NULL,
    file char(36) NULL,
    category int unsigned NULL,
    status varchar(255) NULL,
    person int unsigned NULL,
    organization int unsigned NULL,
    planned_expense int unsigned NULL,
    date date NULL,
    expiry_date date NULL,
    tags json NULL,
    notes text NULL,
    created_at datetime NULL
);
INSERT INTO documents (id, title, file, category, status, person, organization, date, tags, created_at) VALUES
    (12, 'Bolletta luce di ottobre', 'aaaaaaaa-0000-0000-0000-000000000001', 1, 'active', 3, 7, '2026-10-01', '["luce", "2026"]', '2026-10-01 09:00:00'),
    (13, 'Contratto luce', 'aaaaaaaa-0000-0000-0000-000000000002', 1, 'active', 5, 7, '2024-03-15', NULL, '2026-09-01 09:00:00'),
    (14, 'Tessera sanitaria', 'aaaaaaaa-0000-0000-0000-000000000003', 4, 'active', 8, NULL, NULL, NULL, NULL),
    (15, 'Documento senza file', NULL, 4, 'active', NULL, NULL, NULL, NULL, NULL);

CREATE TABLE utilities (
    id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    type varchar(255) NULL,
    status varchar(255) NULL,
    organization int unsigned NULL,
    home int unsigned NULL,
    person int unsigned NULL,
    customer_code varchar(255) NULL,
    supply_point_code varchar(255) NULL,
    activation_date date NULL,
    notes text NULL
);
INSERT INTO utilities (id, type, status, organization, person, customer_code) VALUES
    (2, 'electricity', 'active', 7, 5, '40291837');

CREATE TABLE utilities_documents (
    id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    utilities_id int unsigned NULL,
    documents_id int unsigned NULL
);
INSERT INTO utilities_documents (utilities_id, documents_id) VALUES
    (2, 12),
    (2, 13),
    (2, 99);

CREATE TABLE reminders (
    id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title varchar(255) NULL,
    due_date date NULL,
    status varchar(255) NULL,
    person int unsigned NULL,
    is_automatic tinyint(1) NULL,
    source_field varchar(255) NULL,
    reminder_group int unsigned NULL,
    notifications_sent json NULL,
    notes text NULL
);
INSERT INTO reminders (id, title, due_date, status, person, is_automatic) VALUES
    (5, 'Leggere il contatore', '2026-11-10', 'open', 5, 0),
    (6, 'Revisione auto', '2026-12-01', 'open', NULL, 1);

CREATE TABLE reminders_related_to (
    id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    reminders_id int unsigned NULL,
    collection varchar(255) NULL,
    item varchar(255) NULL
);
INSERT INTO reminders_related_to (reminders_id, collection, item) VALUES
    (5, 'utilities', '2'),
    (6, 'vehicles', '4');

CREATE TABLE family_settings (
    id int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
    date_updated timestamp NULL,
    mail_inbox_recipient varchar(255) NULL,
    mail_inbox_notify json NULL,
    anthropic_api_key text NULL,
    reminders_cron varchar(255) NULL,
    family_name varchar(255) NULL
);
INSERT INTO family_settings (date_updated, mail_inbox_recipient, mail_inbox_notify, anthropic_api_key, reminders_cron, family_name) VALUES
    ('2026-10-20 08:00:00', 'documenti@example.com', '["laura.verdi@example.com"]', 'sk-not-a-key', '0 8 * * *', 'Verdi');
