# Entrega completa — Canvi Pay
Inclui:
- SDK `canvi-pay/` (implementado)
- `laravel-app/` com Models, Seeders, Migrations, Controllers e Jobs
- Docker starter (Nginx + PHP-FPM + MySQL)
- Postman collection (`laravel-app/postman_collection.json`)
- CI GitHub Actions (`.github/workflows/phpunit.yml`) para o SDK

## Como usar rapidamente
1) Suba Docker e crie seu app Laravel dentro de `laravel-app/demo` (ou adapte para seu app).
2) Copie os arquivos de `laravel-app/` para seu projeto, rode `php artisan migrate && php artisan db:seed`.
3) No SDK (ou no monorepo), rode `composer install` e `./vendor/bin/phpunit`.