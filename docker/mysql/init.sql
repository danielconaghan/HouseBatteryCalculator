-- Create separate databases for each service.
-- The MySQL container runs this once on first startup.

CREATE DATABASE IF NOT EXISTS energy_service
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS energy_bff
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON energy_service.* TO 'sail'@'%';
GRANT ALL PRIVILEGES ON energy_bff.*      TO 'sail'@'%';

FLUSH PRIVILEGES;
