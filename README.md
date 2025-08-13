# R2Soft Canvi - Ambiente Docker (PHP 8.1 + Nginx + PHP-FPM)

Este projeto inclui um ambiente Docker para desenvolvimento com:
- PHP 8.1 (FPM)
- Nginx servindo `public/`
- Composer (container dedicado)
- Xdebug opcional

Pré-requisitos
- Docker e Docker Compose instalados

Estrutura esperada
- A aplicação web (se houver) deve expor os arquivos públicos em `public/` (ex.: `public/index.php`).
- Para uso como biblioteca/SDK, o Nginx é opcional; ainda assim, o serviço está disponível caso você deseje expor endpoints (ex.: webhooks).

Comandos básicos

1) Build das imagens
- docker compose build --no-cache

2) Subir os serviços
- docker compose up -d

3) Instalar dependências
- docker compose run --rm composer install

4) Acessar via navegador
- http://localhost:8383

5) Executar comandos PHP dentro do container
- docker compose exec php php -v
- docker compose exec php php -m
- docker compose exec php php path/para/script.php

6) Rodar testes (se houver PHPUnit no composer.json)
- docker compose exec php ./vendor/bin/phpunit

7) Logs
- docker compose logs -f php
- docker compose logs -f nginx

Xdebug

Ativar Xdebug
1. Habilite a flag de build (se necessário):
    - No docker-compose.yml, em build.args: `ENABLE_XDEBUG: "1"`
    - Rebuild: `docker compose build --no-cache`
2. Defina o modo em runtime:
    - `export XDEBUG_MODE=debug`
3. Opcional: configure host/porta (default já definido):
    - `export XDEBUG_CLIENT_HOST=host.docker.internal`
    - `export XDEBUG_CLIENT_PORT=9003`
4. Suba os serviços:
    - `docker compose up -d`

Dicas
- Em Linux, `host.docker.internal` pode não resolver. Use o IP do host ou `gateway` da rede Docker:
    - `ip -4 addr show docker0` para descobrir o IP do host ou
    - defina manualmente: `XDEBUG_CLIENT_HOST=172.17.0.1`.

Manutenção
- Derrubar serviços: `docker compose down`
- Derrubar e remover volumes anônimos (cuidado): `docker compose down -v`
- Atualizar dependências: `docker compose run --rm composer update`

Resolução de problemas
- Erro ao copiar *.ini no build: verifique se `php_ini_configuration.ini` e `xdebug_configuration.ini` existem na raiz do projeto.
- 404 no Nginx: garanta que exista `public/index.php` ou ajuste `root` no arquivo `NginxConfigurationFile.conf`.
- Xdebug não conecta: checar `XDEBUG_MODE`, `XDEBUG_CONFIG` e firewall local (porta 9003).

Licença
- Consulte o arquivo de licença do projeto.