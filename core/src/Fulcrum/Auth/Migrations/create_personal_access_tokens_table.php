<?php

/**
 * Migration schema for personal_access_tokens table.
 *
 * CREATE TABLE `personal_access_tokens` (
 *   `id` bigint unsigned NOT NULL AUTO_INCREMENT,
 *   `tokenable_type` varchar(255) NOT NULL,
 *   `tokenable_id` varchar(255) NOT NULL,
 *   `name` varchar(255) NOT NULL,
 *   `token` varchar(64) NOT NULL,
 *   `abilities` text,
 *   `last_used_at` timestamp NULL DEFAULT NULL,
 *   `expires_at` timestamp NULL DEFAULT NULL,
 *   `created_at` timestamp NULL DEFAULT NULL,
 *   `updated_at` timestamp NULL DEFAULT NULL,
 *   PRIMARY KEY (`id`),
 *   UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
 *   KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 */
