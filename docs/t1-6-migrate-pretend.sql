
 INFO Preparing database. 

 Creating migration table .. 63.85ms DONE

 INFO Running migrations. 

 2026_10_22_090356_create_sessions_table .. 
 ⇂ create table `sessions` (`id` varchar(255) not null, `user_id` bigint unsigned null, `ip_address` varchar(45) null, `user_agent` text null, `payload` longtext not null, `last_activity` int not null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' 
 ⇂ alter table `sessions` add index `sessions_user_id_index`(`user_id`) 
 ⇂ alter table `sessions` add index `sessions_last_activity_index`(`last_activity`) 
 2026_10_22_090357_create_cache_table .. 
 ⇂ create table `cache` (`key` varchar(255) not null, `value` mediumtext not null, `expiration` bigint not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' 
 ⇂ alter table `cache` add index `cache_expiration_index`(`expiration`) 
 ⇂ create table `cache_locks` (`key` varchar(255) not null, `owner` varchar(255) not null, `expiration` bigint not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' 
 ⇂ alter table `cache_locks` add index `cache_locks_expiration_index`(`expiration`) 
 2026_10_22_090358_create_jobs_table .. 
 ⇂ create table `jobs` (`id` bigint unsigned not null auto_increment primary key, `queue` varchar(255) not null, `payload` longtext not null, `attempts` smallint unsigned not null, `reserved_at` int unsigned null, `available_at` int unsigned not null, `created_at` int unsigned not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci' 
 ⇂ alter table `jobs` add index `jobs_queue_index`(`queue`) 
 2026_10_22_090359_create_job_batches_table .. 
 ⇂ create table `job_batches` (`id` varchar(255) not null, `name` varchar(255) not null, `total_jobs` int not null, `pending_jobs` int not null, `failed_jobs` int not null, `failed_job_ids` longtext not null, `options` mediumtext null, `cancelled_at` int null, `created_at` int not null, `finished_at` int null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci' 
 2026_10_22_090400_create_failed_jobs_table .. 
 ⇂ create table `failed_jobs` (`id` bigint unsigned not null auto_increment primary key, `uuid` varchar(255) not null, `connection` varchar(255) not null, `queue` varchar(255) not null, `payload` longtext not null, `exception` longtext not null, `failed_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci' 
 ⇂ alter table `failed_jobs` add index `failed_jobs_connection_queue_failed_at_index`(`connection`, `queue`, `failed_at`) 
 ⇂ alter table `failed_jobs` add unique `failed_jobs_uuid_unique`(`uuid`) 

