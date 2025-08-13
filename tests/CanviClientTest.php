<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface as Psr18Client;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use R2Soft\Canvi\CanviClient;
use R2Soft\Canvi\Exceptions\ValidationException;

final class CanviClientTest extends TestCase
{
    public function testSuccessEnvelopeReturnsData(): void
    {
        $body = json_encode([
            'code' => 'OK',
            'mensagem' => 'Sucesso',
            'data' => ['id' => 123, 'status' => 'created']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $http = new FakeHttpClient(new FakeResponse(200, $body));
        $factory = new FakeHttpFactory();
        $client = new CanviClient(
            baseUrl: 'https://example.test',
            apiKey: 'x',
            httpClient: $http,
            requestFactory: $factory,
            streamFactory: $factory
        );

        $res = $client->get('/charges/1');
        self::assertIsArray($res);
        self::assertSame(123, $res['id']);
        self::assertSame('created', $res['status']);
    }

    public function testValidationErrorUsesMensagemAndErros(): void
    {
        $body = json_encode([
            'code' => 'VAL_ERR',
            'mensagem' => 'Payload inválido',
            'erros' => ['amount' => 'valor mínimo 100']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $http = new FakeHttpClient(new FakeResponse(400, $body));
        $factory = new FakeHttpFactory();
        $client = new CanviClient(
            baseUrl: 'https://example.test',
            apiKey: 'x',
            httpClient: $http,
            requestFactory: $factory,
            streamFactory: $factory
        );

        $this->expectException(ValidationException::class);
        try {
            $client->post('/charges/boleto', ['amount' => 50]);
        } catch (ValidationException $e) {
            self::assertSame('Payload inválido', $e->getMessage());
            self::assertArrayHasKey('amount', $e->fields);
            throw $e;
        }
    }
}

// ---- Fakes PSR-18/17/7 ----

final class FakeHttpClient implements Psr18Client
{
    public function __construct(private ResponseInterface $response) {}
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}

final class FakeHttpFactory implements RequestFactoryInterface, StreamFactoryInterface
{
    public function createRequest(string $method, \Psr\Http\Message\UriInterface|string $uri): RequestInterface
    {
        return new FakeRequest($method, (string)$uri);
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return new FakeStream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return new FakeStream(file_get_contents($filename) ?: '');
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        return new FakeStream(stream_get_contents($resource) ?: '');
    }
}

final class FakeRequest implements RequestInterface
{
    public function __construct(private string $method, private string $uri, private array $headers = [], private ?StreamInterface $body = null) {}

    public function getRequestTarget(): string { return $this->uri; }
    public function withRequestTarget(string $requestTarget): RequestInterface { $new = clone $this; $new->uri = (string)$requestTarget; return $new; }
    public function getMethod(): string { return $this->method; }
    public function withMethod(string $method): RequestInterface { $new = clone $this; $new->method = $method; return $new; }
    public function getUri(): \Psr\Http\Message\UriInterface { return new class($this->uri) implements \Psr\Http\Message\UriInterface {
        public function __construct(private string $u) {}
        public function getScheme(): string {return '';} public function getAuthority(): string {return '';} public function getUserInfo(): string {return '';} public function getHost(): string {return '';} public function getPort(): ?int {return null;} public function getPath(): string {return $this->u;} public function getQuery(): string {return '';} public function getFragment(): string {return '';} public function withScheme(string $s): self {return $this;} public function withUserInfo(string $u, ?string $p = null): self {return $this;} public function withHost(string $h): self {return $this;} public function withPort(?int $p): self {return $this;} public function withPath(string $p): self {return $this;} public function withQuery(string $q): self {return $this;} public function withFragment(string $f): self {return $this;} public function __toString(): string {return $this->u;}
    }; }
    public function withUri(\Psr\Http\Message\UriInterface $uri, bool $preserveHost = false): RequestInterface { return $this; }
    public function getProtocolVersion(): string { return '1.1'; }
    public function withProtocolVersion(string $version): RequestInterface { return $this; }
    public function getHeaders(): array { return $this->headers; }
    public function hasHeader(string $name): bool { return isset($this->headers[$name]); }
    public function getHeader(string $name): array { return $this->headers[$name] ?? []; }
    public function getHeaderLine(string $name): string { $v = $this->getHeader($name); return is_array($v) ? implode(',', $v) : (string)$v; }
    public function withHeader(string $name, $value): RequestInterface { $new = clone $this; $new->headers[$name] = (array)$value; return $new; }
    public function withAddedHeader(string $name, $value): RequestInterface { $new = clone $this; $new->headers[$name] = array_merge($new->headers[$name] ?? [], (array)$value); return $new; }
    public function withoutHeader(string $name): RequestInterface { $new = clone $this; unset($new->headers[$name]); return $new; }
    public function getBody(): StreamInterface { return $this->body ??= new FakeStream(''); }
    public function withBody(StreamInterface $body): RequestInterface { $new = clone $this; $new->body = $body; return $new; }
}

final class FakeResponse implements ResponseInterface
{
    public function __construct(private int $status, private string $body, private array $headers = ['Content-Type' => ['application/json']]) {}
    public function getProtocolVersion(): string {return '1.1';}
    public function withProtocolVersion(string $version): ResponseInterface {return $this;}
    public function getHeaders(): array {return $this->headers;}
    public function hasHeader(string $name): bool {return isset($this->headers[$name]);}
    public function getHeader(string $name): array {return $this->headers[$name] ?? [];}
    public function getHeaderLine(string $name): string {$v=$this->getHeader($name);return is_array($v)?implode(',', $v):(string)$v;}
    public function withHeader(string $name, $value): ResponseInterface {$new=clone $this;$new->headers[$name]=(array)$value;return $new;}
    public function withAddedHeader(string $name, $value): ResponseInterface {$new=clone $this;$new->headers[$name]=array_merge($new->headers[$name]??[],(array)$value);return $new;}
    public function withoutHeader(string $name): ResponseInterface {$new=clone $this;unset($new->headers[$name]);return $new;}
    public function getBody(): StreamInterface {return new FakeStream($this->body);}    
    public function withBody(StreamInterface $body): ResponseInterface {$new=clone $this; $new->body=(string)$body; return $new;}
    public function getStatusCode(): int {return $this->status;}
    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface {$new=clone $this;$new->status=$code;return $new;}
    public function getReasonPhrase(): string {return '';} 
}

final class FakeStream implements StreamInterface
{
    public function __construct(private string $content) {}
    public function __toString(): string {return $this->content;}
    public function close(): void {}
    public function detach(){return null;}
    public function getSize(): ?int {return strlen($this->content);}    
    public function tell(): int {return 0;}
    public function eof(): bool {return true;}
    public function isSeekable(): bool {return false;}
    public function seek(int $offset, int $whence = SEEK_SET): void {}
    public function rewind(): void {}
    public function isWritable(): bool {return false;}
    public function write(string $string): int {return 0;}
    public function isReadable(): bool {return true;}
    public function read(int $length): string {return $this->content;}
    public function getContents(): string {return $this->content;}
    public function getMetadata(?string $key = null): mixed {return null;}
}
