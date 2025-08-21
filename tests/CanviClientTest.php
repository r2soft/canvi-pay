<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface as Psr18Client;

use Psr\Log\LoggerInterface;
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
        // Dados de erro de validação retornados pela API
        $errorData = [
            'code' => 'VAL_ERR',
            'mensagem' => 'Payload inválido',
            'erros' => ['amount' => 'valor mínimo 100']
        ];
        
        // Cria uma resposta de erro 400 com os dados de validação
        $errorResponse = new FakeResponse(
            400, 
            json_encode($errorData), 
            ['Content-Type' => 'application/json']
        );
        
        // Cria uma resposta de autenticação bem-sucedida
        $authResponse = new FakeResponse(
            200, 
            json_encode([
                'access_token' => 'test-token',
                'token_type' => 'bearer',
                'expires_in' => 3600
            ]), 
            ['Content-Type' => 'application/json']
        );
        
        // Configura o cliente HTTP falso para retornar as respostas apropriadas
        $http = new FakeHttpClient($errorResponse, function(RequestInterface $request) use ($authResponse, $errorResponse) {
            $uri = (string)$request->getUri();
            
            // Para requisições de autenticação, retorna a resposta de autenticação
            if (str_ends_with($uri, '/bt/token')) {
                return $authResponse;
            }
            
            // Para outras requisições, retorna o erro de validação
            return $errorResponse;
        });
        
        // Cria as fábricas necessárias
        $factory = new FakeHttpFactory();
        
        // Cria o cliente Canvi com o cliente HTTP falso
        $client = new CanviClient(
            baseUrl: 'https://example.test',
            apiKey: 'x',
            apiSecret: 'y',
            httpClient: $http,
            requestFactory: $factory,
            streamFactory: $factory,
            logger: null // Desativa o logger para simplificar a saída
        );

        // Configura as expectativas de exceção
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Payload inválido');
        
        // Executa a chamada que deve lançar a exceção
        $client->post('/charges/boleto', ['amount' => 50]);
        
        // Se chegou aqui, a exceção não foi lançada
        $this->fail('Expected ValidationException was not thrown');
    }
}

// ---- Fakes PSR-18/17/7 ----

final class FakeHttpClient implements Psr18Client
{
    private $response;
    private $requests = [];
    private $responseHandler;

    public function __construct(ResponseInterface $response, ?callable $responseHandler = null) 
    {
        $this->response = $response;
        $this->responseHandler = $responseHandler;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        
        // If a custom response handler is provided, use it
        if ($this->responseHandler) {
            $response = ($this->responseHandler)($request, $this->response);
            if ($response instanceof ResponseInterface) {
                return $response;
            }
        }
        
        return $this->response;
    }
    
    public function getLastRequest(): ?RequestInterface
    {
        return end($this->requests) ?: null;
    }
    
    public function getRequests(): array
    {
        return $this->requests;
    }
}

final class FakeHttpFactory implements RequestFactoryInterface, StreamFactoryInterface
{
    /**
     * @param string $method
     * @param \Psr\Http\Message\UriInterface|string $uri
     * @return RequestInterface
     */
    public function createRequest(string $method, $uri): RequestInterface
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
    private $method;
    private $uri;
    private $headers;
    private $body;
    
    public function __construct(string $method, string $uri, array $headers = [], ?StreamInterface $body = null) 
    {
        $this->method = $method;
        $this->uri = $uri;
        $this->headers = $headers;
        $this->body = $body;
    }

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

final class FakeStream implements StreamInterface
{
    private string $content;
    private int $position = 0;
    private bool $isReadable = true;
    private bool $isWritable = true; // Make writable by default for testing
    private bool $isSeekable = true;
    
    public function __construct(string $content) 
    {
        $this->content = $content;
        $this->isReadable = true;
        $this->isWritable = true; // Keep writable for testing
        $this->isSeekable = true;
    }
    
    public function __toString(): string
    {
        return $this->content;
    }
    
    public function close(): void 
    {
        $this->isReadable = false;
        $this->isWritable = false;
    }
    
    public function detach()
    {
        $content = $this->content;
        $this->content = '';
        $this->isReadable = false;
        $this->isWritable = false;
        return $content;
    }
    
    public function getSize(): ?int
    {
        return strlen($this->content);
    }
    
    public function tell(): int
    {
        return $this->position;
    }
    
    public function eof(): bool
    {
        return $this->position >= strlen($this->content);
    }
    
    public function isSeekable(): bool
    {
        return $this->isSeekable;
    }
    
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable) {
            throw new \RuntimeException('Stream is not seekable');
        }
        
        $length = strlen($this->content);
        
        switch ($whence) {
            case SEEK_SET:
                $this->position = $offset;
                break;
            case SEEK_CUR:
                $this->position += $offset;
                break;
            case SEEK_END:
                $this->position = $length + $offset;
                break;
            default:
                throw new \InvalidArgumentException('Invalid whence');
        }
        
        if ($this->position < 0) {
            $this->position = 0;
        } elseif ($this->position > $length) {
            $this->position = $length;
        }
    }
    
    public function rewind(): void
    {
        $this->position = 0;
    }
    
    public function isWritable(): bool
    {
        return $this->isWritable;
    }
    
    public function write(string $string): int
    {
        if (!$this->isWritable) {
            throw new \RuntimeException('Cannot write to a non-writable stream');
        }
        
        $length = strlen($string);
        $before = substr($this->content, 0, $this->position);
        $after = ($this->position < strlen($this->content)) 
            ? substr($this->content, $this->position + $length) 
            : '';
            
        $this->content = $before . $string . $after;
        $this->position += $length;
        
        return $length;
    }
    
    public function isReadable(): bool
    {
        return $this->isReadable;
    }
    
    public function read(int $length): string
    {
        if (!$this->isReadable) {
            throw new \RuntimeException('Cannot read from non-readable stream');
        }
        
        $result = substr($this->content, $this->position, $length);
        $this->position = min($this->position + $length, strlen($this->content));
        return $result;
    }
    
    public function getContents(): string
    {
        if (!$this->isReadable) {
            throw new \RuntimeException('Cannot read from non-readable stream');
        }
        
        $remaining = substr($this->content, $this->position);
        $this->position = strlen($this->content);
        return $remaining;
    }
    
    public function getMetadata(?string $key = null)
    {
        $metadata = [
            'timed_out' => false,
            'blocked' => true,
            'eof' => $this->eof(),
            'unread_bytes' => 0,
            'stream_type' => 'string',
            'wrapper_type' => 'PHP',
            'mode' => 'r',
            'seekable' => $this->isSeekable,
            'uri' => 'php://memory',
        ];
        
        if ($key === null) {
            return $metadata;
        }
        
        return $metadata[$key] ?? null;
    } 
}

final class FakeResponse implements ResponseInterface
{
    private array $headers = [];
    private string $bodyContent;
    private string $reasonPhrase = '';
    private string $protocolVersion = '1.1';

    public function __construct(
        private int $statusCode = 200,
        string $body = '',
        array $headers = []
    ) {
        // Store the body content to create a new stream each time getBody() is called
        $this->bodyContent = $body;
        
        // Normalize headers to be case-insensitive
        foreach ($headers as $name => $value) {
            $this->headers[strtolower($name)] = (array)$value;
        }
        
        // Ensure Content-Type header is set
        if (!isset($this->headers['content-type'])) {
            $this->headers['content-type'] = ['application/json'];
        }
        
        // Set Content-Length if not set
        if (!isset($this->headers['content-length'])) {
            $this->headers['content-length'] = [strlen($body)];
        }
    }
    
    public function getBody(): StreamInterface 
    {
        // Always return a new stream to allow multiple reads
        return new FakeStream($this->bodyContent);
    }

    public function getProtocolVersion(): string 
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): ResponseInterface
    {
        $new = clone $this;
        $new->protocolVersion = $version;
        return $new;
    }

    public function getHeaders(): array 
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool 
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getHeader(string $name): array 
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? [];
    }

    public function getHeaderLine(string $name): string 
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): ResponseInterface 
    {
        $new = clone $this;
        $new->headers[strtolower($name)] = (array)$value;
        return $new;
    }

    public function withAddedHeader(string $name, $value): ResponseInterface 
    {
        $new = clone $this;
        $name = strtolower($name);
        $new->headers[$name] = array_merge($this->getHeader($name), (array)$value);
        return $new;
    }

    public function withoutHeader(string $name): ResponseInterface 
    {
        $new = clone $this;
        unset($new->headers[strtolower($name)]);
        return $new;
    }

    public function withBody(StreamInterface $body): ResponseInterface 
    {
        $new = clone $this;
        $new->bodyContent = (string) $body;
        return $new;
    }

    public function getStatusCode(): int 
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface 
    {
        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase;
        return $new;
    }

    public function getReasonPhrase(): string 
    {
        return $this->reasonPhrase;
    } 
}
