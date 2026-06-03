/_!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT _/;
/_!40101 SET NAMES utf8 _/;
/_!50503 SET NAMES utf8mb4 _/;
/_!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE _/;
/_!40103 SET TIME_ZONE='+00:00' _/;
/_!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 _/;
/_!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' _/;
/_!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 _/;

CREATE TABLE IF NOT EXISTS `accounts` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'customer',
`is_active` tinyint(1) NOT NULL DEFAULT '1',
`email_verified_at` timestamp NULL DEFAULT NULL,
`remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
`deleted_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `accounts_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `addresses` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`account_id` bigint unsigned NOT NULL,
`recipient_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
`recipient_phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
`province_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`district_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`ward_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`detail_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`is_default` tinyint(1) NOT NULL DEFAULT '0',
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `addresses_account_id_is_default_index` (`account_id`,`is_default`),
CONSTRAINT `addresses_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_chat_evaluations` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`message_id` bigint unsigned NOT NULL,
`groundedness_score` decimal(4,3) NOT NULL,
`relevance_score` decimal(4,3) NOT NULL,
`has_hallucination_risk` tinyint(1) NOT NULL,
`verdict` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
`risk_flags` json DEFAULT NULL,
`evaluated_at` timestamp NOT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `ai_chat_evaluations_message_id_unique` (`message_id`),
CONSTRAINT `ai_chat_evaluations_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `ai_chat_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_chat_feedback` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`message_id` bigint unsigned NOT NULL,
`session_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
`account_id` bigint unsigned DEFAULT NULL,
`rating` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `ai_chat_feedback_message_id_unique` (`message_id`),
KEY `ai_chat_feedback_account_created_at_index` (`account_id`,`created_at`),
KEY `ai_chat_feedback_session_created_at_index` (`session_id`,`created_at`),
CONSTRAINT `ai_chat_feedback_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL,
CONSTRAINT `ai_chat_feedback_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `ai_chat_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_chat_messages` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`session_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
`account_id` bigint unsigned DEFAULT NULL,
`question` text COLLATE utf8mb4_unicode_ci NOT NULL,
`answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
`model_version` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
`retrieval_strategy` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
`retrieval_top_score` decimal(6,4) DEFAULT NULL,
`retrieval_matched` tinyint(1) NOT NULL,
`retrieved_books` json DEFAULT NULL,
`token_usage` json DEFAULT NULL,
`latency_ms` int unsigned DEFAULT NULL,
`error_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `ai_chat_messages_session_created_at_index` (`session_id`,`created_at`),
KEY `ai_chat_messages_account_created_at_index` (`account_id`,`created_at`),
KEY `ai_chat_messages_retrieval_matched_created_at_index` (`retrieval_matched`,`created_at`),
CONSTRAINT `ai_chat_messages_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `authors` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`created_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `authors_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=895 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `banners` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`public_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`image_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
`sort_order` int unsigned NOT NULL DEFAULT '0',
`is_active` tinyint(1) NOT NULL DEFAULT '1',
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `banners_public_id_unique` (`public_id`),
KEY `banners_active_sort_index` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `books` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`supplier_id` bigint unsigned DEFAULT NULL,
`publisher_id` bigint unsigned DEFAULT NULL,
`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`sku` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`thumbnail` text COLLATE utf8mb4_unicode_ci,
`original_price` decimal(10,2) NOT NULL,
`selling_price` decimal(10,2) NOT NULL,
`review_count` int unsigned NOT NULL DEFAULT '0',
`average_rating` decimal(3,2) NOT NULL DEFAULT '0.00',
`is_active` tinyint(1) NOT NULL DEFAULT '1',
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `books_slug_unique` (`slug`),
UNIQUE KEY `books_sku_unique` (`sku`),
KEY `books_supplier_id_foreign` (`supplier_id`),
KEY `books_publisher_id_foreign` (`publisher_id`),
KEY `books_is_active_index` (`is_active`),
KEY `books_selling_price_index` (`selling_price`),
KEY `books_average_rating_index` (`average_rating`),
KEY `books_created_at_index` (`created_at`),
CONSTRAINT `books_publisher_id_foreign` FOREIGN KEY (`publisher_id`) REFERENCES `publishers` (`id`) ON DELETE SET NULL,
CONSTRAINT `books_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
CONSTRAINT `books_prices` CHECK (((`original_price` > 0) and (`selling_price` > 0)))
) ENGINE=InnoDB AUTO_INCREMENT=1758 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_authors` (
`book_id` bigint unsigned NOT NULL,
`author_id` bigint unsigned NOT NULL,
PRIMARY KEY (`book_id`,`author_id`),
KEY `book_authors_author_id_foreign` (`author_id`),
CONSTRAINT `book_authors_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`) ON DELETE CASCADE,
CONSTRAINT `book_authors_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_categories` (
`book_id` bigint unsigned NOT NULL,
`category_id` bigint unsigned NOT NULL,
PRIMARY KEY (`book_id`,`category_id`),
KEY `book_categories_category_id_foreign` (`category_id`),
CONSTRAINT `book_categories_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
CONSTRAINT `book_categories_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_details` (
`book_id` bigint unsigned NOT NULL,
`description` text COLLATE utf8mb4_unicode_ci,
`language` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
`translator` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`publication_year` smallint DEFAULT NULL,
`weight` decimal(8,2) DEFAULT NULL,
`dimensions` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`num_pages` int unsigned DEFAULT NULL,
`format` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
PRIMARY KEY (`book_id`),
CONSTRAINT `book_details_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_images` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`book_id` bigint unsigned NOT NULL,
`public_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`image_url` text COLLATE utf8mb4_unicode_ci NOT NULL,
`sort_order` int unsigned NOT NULL DEFAULT '0',
`created_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `book_images_book_id_foreign` (`book_id`),
CONSTRAINT `book_images_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2568 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `book_interaction_events` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`account_id` bigint unsigned NOT NULL,
`book_id` bigint unsigned NOT NULL,
`event_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
`source` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`created_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `bie_account_type_created_at_index` (`account_id`,`event_type`,`created_at`),
KEY `bie_account_book_created_at_index` (`account_id`,`book_id`,`created_at`),
KEY `bie_book_type_created_at_index` (`book_id`,`event_type`,`created_at`),
CONSTRAINT `book_interaction_events_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
CONSTRAINT `book_interaction_events_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache` (
`key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
`expiration` int NOT NULL,
PRIMARY KEY (`key`),
KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
`key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`expiration` int NOT NULL,
PRIMARY KEY (`key`),
KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `carts` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`account_id` bigint unsigned DEFAULT NULL,
`guest_token_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`guest_token_expires_at` timestamp NULL DEFAULT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `carts_account_id_unique` (`account_id`),
UNIQUE KEY `carts_guest_token_hash_unique` (`guest_token_hash`),
CONSTRAINT `carts_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cart_items` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`cart_id` bigint unsigned NOT NULL,
`book_id` bigint unsigned NOT NULL,
`quantity` int unsigned NOT NULL,
`selected` tinyint(1) NOT NULL DEFAULT '1',
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `cart_items_cart_id_book_id_unique` (`cart_id`,`book_id`),
KEY `cart_items_book_id_foreign` (`book_id`),
CONSTRAINT `cart_items_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
CONSTRAINT `cart_items_cart_id_foreign` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`parent_id` bigint unsigned DEFAULT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `categories_slug_unique` (`slug`),
KEY `categories_parent_id_foreign` (`parent_id`),
CONSTRAINT `categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_import_rows` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`data` json NOT NULL,
`import_id` bigint unsigned NOT NULL,
`validation_error` text COLLATE utf8mb4_unicode_ci,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `failed_import_rows_import_id_foreign` (`import_id`),
CONSTRAINT `failed_import_rows_import_id_foreign` FOREIGN KEY (`import_id`) REFERENCES `imports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `failed_jobs` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
`queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
`payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
`exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
`failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
PRIMARY KEY (`id`),
UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `imports` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`completed_at` timestamp NULL DEFAULT NULL,
`file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`importer` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`processed_rows` int unsigned NOT NULL DEFAULT '0',
`total_rows` int unsigned NOT NULL,
`successful_rows` int unsigned NOT NULL DEFAULT '0',
`user_id` bigint unsigned NOT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `imports_user_id_foreign` (`user_id`),
CONSTRAINT `imports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventories` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`book_id` bigint unsigned NOT NULL,
`warehouse_id` bigint unsigned NOT NULL,
`quantity` int unsigned NOT NULL DEFAULT '0',
`sold_quantity` int unsigned NOT NULL DEFAULT '0',
`reserved_quantity` int unsigned NOT NULL DEFAULT '0',
`location_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
`last_restocked_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `inventories_book_id_unique` (`book_id`),
KEY `inventories_warehouse_id_foreign` (`warehouse_id`),
CONSTRAINT `inventories_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
CONSTRAINT `inventories_warehouse_id_foreign` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE RESTRICT,
CONSTRAINT `inventories_reserved_within_quantity_check` CHECK ((`reserved_quantity` <= `quantity`))
) ENGINE=InnoDB AUTO_INCREMENT=1757 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
`attempts` tinyint unsigned NOT NULL,
`reserved_at` int unsigned DEFAULT NULL,
`available_at` int unsigned NOT NULL,
`created_at` int unsigned NOT NULL,
PRIMARY KEY (`id`),
KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_batches` (
`id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`total_jobs` int NOT NULL,
`pending_jobs` int NOT NULL,
`failed_jobs` int NOT NULL,
`failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
`options` mediumtext COLLATE utf8mb4_unicode_ci,
`cancelled_at` int DEFAULT NULL,
`created_at` int NOT NULL,
`finished_at` int DEFAULT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migrations` (
`id` int unsigned NOT NULL AUTO_INCREMENT,
`migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`batch` int NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
`id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
`type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`notifiable_id` bigint unsigned NOT NULL,
`data` text COLLATE utf8mb4_unicode_ci NOT NULL,
`read_at` timestamp NULL DEFAULT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`account_id` bigint unsigned NOT NULL,
`checkout_idempotency_key` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`shipping_method_id` bigint unsigned NOT NULL,
`total_amount` decimal(15,2) NOT NULL,
`shipping_fee` decimal(15,2) NOT NULL,
`final_amount` decimal(15,2) NOT NULL,
`shipping_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
`shipping_phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
`shipping_address` text COLLATE utf8mb4_unicode_ci NOT NULL,
`payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`payment_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`payment_expires_at` timestamp NULL DEFAULT NULL,
`refund_deadline_at` timestamp NULL DEFAULT NULL,
`note` text COLLATE utf8mb4_unicode_ci,
`current_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `orders_account_checkout_idempotency_unique` (`account_id`,`checkout_idempotency_key`),
KEY `orders_shipping_method_id_foreign` (`shipping_method_id`),
KEY `orders_current_status_index` (`current_status`),
KEY `orders_payment_status_index` (`payment_status`),
KEY `orders_created_at_index` (`created_at`),
KEY `orders_account_id_current_status_index` (`account_id`,`current_status`),
CONSTRAINT `orders_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
CONSTRAINT `orders_shipping_method_id_foreign` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`order_id` bigint unsigned NOT NULL,
`book_id` bigint unsigned NOT NULL,
`promotion_item_id` bigint unsigned DEFAULT NULL,
`promotion_id` bigint unsigned DEFAULT NULL,
`price` decimal(15,2) NOT NULL,
`quantity` int unsigned NOT NULL,
`total_price` decimal(15,2) NOT NULL,
`discount_amount` decimal(15,2) DEFAULT NULL,
`is_reviewed` tinyint(1) NOT NULL DEFAULT '0',
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `order_items_order_id_foreign` (`order_id`),
KEY `order_items_book_id_foreign` (`book_id`),
KEY `order_items_promotion_item_id_foreign` (`promotion_item_id`),
KEY `order_items_promotion_id_foreign` (`promotion_id`),
KEY `order_items_is_reviewed_index` (`is_reviewed`),
CONSTRAINT `order_items_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE RESTRICT,
CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
CONSTRAINT `order_items_promotion_id_foreign` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE SET NULL,
CONSTRAINT `order_items_promotion_item_id_foreign` FOREIGN KEY (`promotion_item_id`) REFERENCES `promotion_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_timelines` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`order_id` bigint unsigned NOT NULL,
`status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
`note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`actor` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`created_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `order_timelines_order_id_foreign` (`order_id`),
CONSTRAINT `order_timelines_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
`email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`created_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_transactions` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`order_id` bigint unsigned NOT NULL,
`gateway` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
`gateway_txn_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
`amount` decimal(15,2) NOT NULL,
`status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
`payload` json DEFAULT NULL,
`completed_at` timestamp NULL DEFAULT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `payment_transactions_gateway_gateway_txn_id_unique` (`gateway`,`gateway_txn_id`),
KEY `payment_transactions_order_id_status_index` (`order_id`,`status`),
CONSTRAINT `payment_transactions_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`tokenable_id` bigint unsigned NOT NULL,
`name` text COLLATE utf8mb4_unicode_ci NOT NULL,
`token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
`abilities` text COLLATE utf8mb4_unicode_ci,
`last_used_at` timestamp NULL DEFAULT NULL,
`expires_at` timestamp NULL DEFAULT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `promotions` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
`start_at` timestamp NOT NULL,
`end_at` timestamp NOT NULL,
`status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `promotions_status_index` (`status`),
KEY `promotions_start_at_end_at_index` (`start_at`,`end_at`),
CONSTRAINT `promotions_end_after_start_check` CHECK ((`end_at` > `start_at`))
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `promotion_allocations` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`promotion_item_id` bigint unsigned NOT NULL,
`account_id` bigint unsigned NOT NULL,
`order_id` bigint unsigned DEFAULT NULL,
`order_item_id` bigint unsigned DEFAULT NULL,
`quantity` int unsigned NOT NULL,
`status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `promotion_allocations_account_id_foreign` (`account_id`),
KEY `promotion_allocations_promotion_item_id_account_id_status_index` (`promotion_item_id`,`account_id`,`status`),
KEY `promotion_allocations_order_id_index` (`order_id`),
KEY `promotion_allocations_order_item_id_index` (`order_item_id`),
CONSTRAINT `promotion_allocations_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
CONSTRAINT `promotion_allocations_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
CONSTRAINT `promotion_allocations_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL,
CONSTRAINT `promotion_allocations_promotion_item_id_foreign` FOREIGN KEY (`promotion_item_id`) REFERENCES `promotion_items` (`id`) ON DELETE CASCADE,
CONSTRAINT `promotion_allocations_quantity_positive_check` CHECK ((`quantity` > 0))
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `promotion_items` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`promotion_id` bigint unsigned NOT NULL,
`book_id` bigint unsigned NOT NULL,
`discount_value` decimal(5,2) NOT NULL,
`stock_limit` int unsigned DEFAULT NULL,
`sold_quantity` int unsigned NOT NULL DEFAULT '0',
`max_quantity_per_user` smallint unsigned DEFAULT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `promotion_items_promotion_id_book_id_unique` (`promotion_id`,`book_id`),
KEY `promotion_items_book_id_foreign` (`book_id`),
CONSTRAINT `promotion_items_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
CONSTRAINT `promotion_items_promotion_id_foreign` FOREIGN KEY (`promotion_id`) REFERENCES `promotions` (`id`) ON DELETE CASCADE,
CONSTRAINT `promotion_items_discount_percent_check` CHECK (((`discount_value` > 0) and (`discount_value` <= 100) and (`discount_value` = floor(`discount_value`)))),
CONSTRAINT `promotion_items_sold_within_stock_check` CHECK (((`stock_limit` is null) or (`sold_quantity` <= `stock_limit`)))
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `publishers` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`created_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `publishers_name_unique` (`name`),
UNIQUE KEY `publishers_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reviews` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`account_id` bigint unsigned NOT NULL,
`book_id` bigint unsigned NOT NULL,
`order_item_id` bigint unsigned NOT NULL,
`rating` decimal(2,1) NOT NULL,
`comment` text COLLATE utf8mb4_unicode_ci,
`status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `reviews_order_item_id_unique` (`order_item_id`),
KEY `reviews_account_id_foreign` (`account_id`),
KEY `reviews_status_index` (`status`),
KEY `reviews_book_id_rating_index` (`book_id`,`rating`),
CONSTRAINT `reviews_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
CONSTRAINT `reviews_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
CONSTRAINT `reviews_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
CONSTRAINT `reviews_rating_range_check` CHECK ((`rating` between 1 and 5))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
`id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`user_id` bigint unsigned DEFAULT NULL,
`ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`user_agent` text COLLATE utf8mb4_unicode_ci,
`payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
`last_activity` int NOT NULL,
PRIMARY KEY (`id`),
KEY `sessions_user_id_index` (`user_id`),
KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shipping_methods` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`is_active` tinyint(1) NOT NULL DEFAULT '1',
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shipping_rates` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`shipping_method_id` bigint unsigned NOT NULL,
`province_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`base_fee` decimal(15,2) NOT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `shipping_rates_shipping_method_id_foreign` (`shipping_method_id`),
KEY `shipping_rates_province_code_index` (`province_code`),
CONSTRAINT `shipping_rates_shipping_method_id_foreign` FOREIGN KEY (`shipping_method_id`) REFERENCES `shipping_methods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `suppliers` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `suppliers_name_unique` (`name`),
UNIQUE KEY `suppliers_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_profiles` (
`account_id` bigint unsigned NOT NULL,
`first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`gender` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
`birthday` date DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`account_id`),
CONSTRAINT `user_profiles_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `warehouses` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
`address` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
`is_active` tinyint(1) NOT NULL DEFAULT '1',
`singleton_key` tinyint unsigned NOT NULL DEFAULT '1',
`created_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `warehouses_singleton_key_unique` (`singleton_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wishlists` (
`id` bigint unsigned NOT NULL AUTO_INCREMENT,
`account_id` bigint unsigned NOT NULL,
`book_id` bigint unsigned NOT NULL,
`created_at` timestamp NULL DEFAULT NULL,
`updated_at` timestamp NULL DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `wishlists_account_id_book_id_unique` (`account_id`,`book_id`),
KEY `wishlists_book_id_index` (`book_id`),
CONSTRAINT `wishlists_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
CONSTRAINT `wishlists_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/_!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') _/;
/_!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') _/;
/_!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) _/;
/_!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT _/;
/_!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) _/;
