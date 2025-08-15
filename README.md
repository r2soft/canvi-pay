# R2Soft Canvi - Ambiente Docker (PHP 8.0)

Este projeto inclui um ambiente Docker para desenvolvimento do pacote (SDK), com:
- PHP 8.0 (contêiner principal)
- Composer (container dedicado)
- Xdebug opcional (para debug CLI)

Pré-requisitos
- Docker e Docker Compose instalados

Estrutura esperada
- Uso como biblioteca/SDK (sem servidor web). Caso precise testar endpoints (ex.: webhooks), você pode usar um servidor embutido do PHP temporariamente.

Comandos básicos

1) Build das imagens
- docker compose build --no-cache

2) Subir o serviço PHP (opcional; você pode usar apenas `run`/`exec`)
- docker compose up -d

3) Instalar dependências
- docker compose run --rm composer install

4) Executar comandos PHP dentro do container
- docker compose exec php php -v
- docker compose exec php php -m
- docker compose exec php php path/para/script.php

5) Rodar testes (se houver PHPUnit no composer.json)
- docker compose exec php ./vendor/bin/phpunit

6) Logs
- docker compose logs -f php

Xdebug (CLI)

Ativar Xdebug
1. Habilite a flag de build:
    - No docker-compose.yml, em build.args: `ENABLE_XDEBUG: "1"`
    - Rebuild do serviço PHP: `docker compose build --no-cache php`
2. Defina o modo em runtime (no seu shell):
    - `export XDEBUG_MODE=debug`
3. Opcional: configure host/porta (default já definido):
    - `export XDEBUG_CLIENT_HOST=host.docker.internal` (em Linux, pode usar `172.17.0.1`)
    - `export XDEBUG_CLIENT_PORT=9003`
4. Suba/Recrie os serviços:
    - `docker compose up -d --force-recreate php`

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

---

Integração com a API Canvi (SDK)

URLs Base
- Sandbox: https://gateway-homol.service-canvi.com.br
- Produção: sob demanda

Estrutura de Resposta
A API retorna geralmente um envelope:
{
  "code": "string",
  "mensagem": "string",
  "data": any
}

Este SDK passa a retornar diretamente o conteúdo de data quando presente (para respostas 2xx). Em erros, as mensagens priorizam os campos mensagem/code.

Instalação de dependências úteis (PSR-18/17)
Se não estiver usando um container DI que injete clientes/factories PSR, recomendamos:
- guzzlehttp/guzzle ^7.9 (HTTP client)
- guzzlehttp/psr7 ^2
- http-interop/http-factory-guzzle ^1.2

Exemplo de bootstrap com Guzzle
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use R2Soft\Canvi\CanviClient;

$httpClient = new GuzzleClient([ 'timeout' => 10 ]);
$factory = new HttpFactory();

$client = new CanviClient(
    baseUrl: 'https://gateway-homol.service-canvi.com.br',
    apiKey: 'SEU_TOKEN',
    httpClient: $httpClient,
    requestFactory: $factory,
    streamFactory: $factory,
    maxRetries: 2,          // opcional (retry 429/5xx)
    retryDelayMs: 300        // opcional
);

Idempotência
Muitos endpoints aceitam idempotência. Basta enviar um UUID no terceiro parâmetro dos métodos create/post:
$payload = [ /* ... */ ];
$res = $client->post('/charges/boleto', $payload, '550e8400-e29b-41d4-a716-446655440000');

Uso dos serviços
// Boleto
$boleto = new R2Soft\Canvi\Services\CanviBoletoService($client);
$resp = $boleto->create([/*payload*/], idempotencyKey: null);

// Pix Dinâmico
$pix = new R2Soft\Canvi\Services\CanviPixService($client);
$resp = $pix->createDynamic([/*payload*/]);

Validação de Webhook
O validador padrão suporta HMAC (sha256) ou verificação com chave pública e valida tolerância temporal.
use R2Soft\Canvi\Webhook\DefaultWebhookValidator;
use R2Soft\Canvi\Webhook\WebhookHandler;

$validator = new DefaultWebhookValidator(
    signatureHeader: 'X-Canvi-Signature',
    secret: 'SEU_SEGREDO_HMAC',     // ou publicKey: "-----BEGIN PUBLIC KEY-----..."
    publicKey: null,
    algorithm: 'sha256',
    tolerance: 300
);

$handler = new WebhookHandler($validator);
$server = $_SERVER;               // cabeçalhos
$raw = file_get_contents('php://input');
$result = $handler->handle($server, $raw);
if ($result['ok']) {
    $event = $result['event'];
    // processe
} else {
    http_response_code(400);
}

Autenticação (geração de token)
Caso sua integração exija gerar um token de acesso a partir de credenciais (api_key/api_secret), use o serviço de autenticação:

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use R2Soft\Canvi\CanviClient;
use R2Soft\Canvi\Services\CanviAuthService;

$http = new GuzzleClient();
$factory = new HttpFactory();
$client = new CanviClient(
    baseUrl: 'https://gateway-homol.service-canvi.com.br',
    apiKey: getenv('CANVI_API_KEY') ?: null,     // opcional; usado se não passar credenciais explicitamente
    apiSecret: getenv('CANVI_API_SECRET') ?: null,
    httpClient: $http,
    requestFactory: $factory,
    streamFactory: $factory,
);

$auth = new CanviAuthService($client, authPath: '/api/v1/auth/token'); // ajuste o path conforme a documentação
$token = $auth->generateToken(); // usa api_key/api_secret do próprio cliente
// ou informe credenciais explicitamente:
// $token = $auth->generateToken(['api_key' => '...', 'api_secret' => '...']);

// A partir daqui, o cliente já enviará Authorization: Bearer <token>
$boleto = new R2Soft\Canvi\Services\CanviBoletoService($client);
$resp = $boleto->create([/* payload */]);

Observações
- Confirme os paths exatos dos endpoints na documentação oficial.
- Este SDK mapeia erros priorizando mensagem/code e campos de validação em erros/fields.
