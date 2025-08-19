<?php
declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use App\Contracts\ComercianteServiceInterface;
use App\Dto\ComercianteCadastroDto;
use App\Exception\ApiRequestException;
use Psr\Http\Client\ClientInterface as HttpClient;
use Psr\Http\Message\RequestFactoryInterface as RequestFactory;
use Psr\Http\Message\StreamFactoryInterface as StreamFactory;

final class canviComercianteService implements ComercianteServiceInterface
{
    public function __construct(
        private HttpClient     $http,
        private RequestFactory $requestFactory,
        private StreamFactory  $streamFactory,
        private string         $baseUrl,
        private ?string        $bearerToken = null // ou use apiKey/apiSecret conforme sua auth
    ) {}

    public function upsert(ComercianteCadastroDto $payload, array $headers = []): array
    {
        $uri = rtrim($this->baseUrl, '/').'/ac/comerciante';

        $body = $this->streamFactory->createStream(json_encode($payload->toArray(), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));

        $request = $this->requestFactory
            ->createRequest('PUT', $uri)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json');

        if ($this->bearerToken) {
            $request = $request->withHeader('Authorization', 'Bearer '.$this->bearerToken);
        }

        foreach ($headers as $k => $v) {
            $request = $request->withHeader($k, $v);
        }

        $request = $request->withBody($body);

        $response = $this->http->sendRequest($request);

        $status = $response->getStatusCode();
        $raw    = (string)$response->getBody();
        $json   = $raw !== '' ? json_decode($raw, true) : null;

        if ($status < 200 || $status >= 300) {
            throw new ApiRequestException(
                message: $json['message'] ?? 'Erro na requisição',
                statusCode: $status,
                responseBody: is_array($json) ? $json : ['raw' => $raw]
            );
        }

        return is_array($json) ? $json : ['raw' => $raw];
    }
}
