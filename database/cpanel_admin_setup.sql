-- EMORIA: configuración administrativa para phpMyAdmin (MySQL/MariaDB).
-- Ejecutar una sola vez después de respaldar la base de datos.

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `role` VARCHAR(20) NOT NULL DEFAULT 'USUARIO' AFTER `password`;

INSERT INTO `users` (`username`, `email`, `password`, `role`, `created_at`, `updated_at`)
VALUES (
    'admin',
    'administracion@emoria.com',
    '$2y$10$FHGPSW.DJI1FCQj3ks93FuACY9SQP7QJ3YMraQr2azpcLfsWxgoQK',
    'ADMIN',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE
    `email` = VALUES(`email`),
    `password` = VALUES(`password`),
    `role` = 'ADMIN',
    `updated_at` = NOW();
