# Repository Overview

- **Root**: c:\xampp\htdocs\lag-int
- **Language/Stack**: PHP 8.1, Apache (Docker), MySQL 8 (docker-compose), Composer
- **App Type**: Custom integration for 3DCart (Shift4Shop) → NetSuite, with auxiliary tooling

## Key Directories
- **public/**: Web root (Apache DocumentRoot) containing entrypoints and test utilities
- **src/**: Application code
  - **Controllers/**: OrderController, StatusController, WebhookController
  - **Middleware/**: AuthMiddleware
  - **Models/**: Customer, Order
  - **Services/**: Auth, BrevoEmail, Email, HubSpot, NetSuite, OrderProcessing, ThreeDCart, UnifiedEmail
  - **Utils/**: Logger, NetSuiteEnvironmentManager, UrlHelper, Validator
- **documentation/**: Setup, testing, troubleshooting, implementation notes
- **tests/**: InstallationTest.php
- **logs/**: Runtime logs (ensure writable)
- **uploads/**: Uploads (ensure writable)
- **scripts/**: Utility scripts (e.g., switch-environment.php)
- **vendor/**: Composer dependencies

## Important Files
- **Dockerfile**: Container build for PHP + Apache
- **docker-compose.yml**: App + MySQL services
- **composer.json / composer.lock**: PHP dependencies
- **.env / .env.example**: Environment variables
- **README.md**: Project level documentation

## Runtime Expectations
- Apache serves from `/var/www/html/public`
- Requires PHP extensions: pdo, pdo_mysql, mbstring, zip, intl
- Depends on MySQL database (service `db` in docker-compose)
- Logs and uploads must be writable by www-data

## Common Tasks
- Build and run with Docker:
  - `docker compose build`
  - `docker compose up -d`
- View logs:
  - App logs under `logs/`
  - Docker: `docker compose logs -f app`
- Access web:
  - http://localhost:8080 (mapped to Apache :80)

## Notes
- For restricted networks, prefer HTTPS for apt sources during Docker build
- Composer install during image build can be skipped if `vendor/` is mounted via volume