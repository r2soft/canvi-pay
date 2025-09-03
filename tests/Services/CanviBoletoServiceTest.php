<?php

declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use R2Soft\Canvi\CanviClient;
use R2Soft\Canvi\Services\CanviBoletoService;

class CanviBoletoServiceTest extends TestCase
{
    private CanviClient|MockObject $clientMock;
    private CanviBoletoService $boletoService;

    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(CanviClient::class);
        $this->boletoService = new CanviBoletoService($this->clientMock);
    }

    public function testCreateBoleto(): void
    {
        $payload = [
            'amount' => 10000, // R$ 100,00
            'due_date' => '2025-12-31',
            'customer' => [
                'name' => 'Cliente de Teste',
                'document' => '12345678909',
                'email' => 'cliente@teste.com',
            ]
        ];
        
        $expectedResponse = [
            'id' => 'bol_123456789',
            'status' => 'pending',
            'barcode' => '12345678901234567890123456789012345678901234',
            'due_date' => '2025-12-31',
            'amount' => 10000
        ];

        $this->clientMock->expects($this->once())
            ->method('post')
            ->with(
                '/bt/boleto',
                $payload,
                null
            )
            ->willReturn($expectedResponse);

        $result = $this->boletoService->create($payload);
        
        $this->assertSame($expectedResponse, $result);
    }

    public function testCreateBoletoWithIdempotencyKey(): void
    {
        $payload = ['amount' => 10000];
        $idempotencyKey = 'test_key_123';
        $expectedResponse = ['id' => 'bol_123'];

        $this->clientMock->expects($this->once())
            ->method('post')
            ->with(
                '/bt/boleto',
                $payload,
                $idempotencyKey
            )
            ->willReturn($expectedResponse);

        $result = $this->boletoService->create($payload, $idempotencyKey);
        $this->assertSame($expectedResponse, $result);
    }

    public function testGetBoleto(): void
    {
        $chargeId = 'bol_123456789';
        $expectedResponse = [
            'id' => $chargeId,
            'status' => 'paid',
            'amount' => 10000,
            'paid_amount' => 10000,
            'paid_at' => '2025-01-01T12:00:00Z'
        ];

        $this->clientMock->expects($this->once())
            ->method('post')
            ->with("/bt/consulta_boleto", ['identificador_externo' => $chargeId])
            ->willReturn($expectedResponse);

        $result = $this->boletoService->get($chargeId);

        $this->assertSame($expectedResponse, $result);
    }

    public function testCancelBoleto(): void
    {
        $chargeId = 'bol_123456789';
        $expectedResponse = [
            'id' => $chargeId,
            'status' => 'canceled',
            'cancellation_date' => '2025-01-01T12:00:00Z'
        ];

        $this->clientMock->expects($this->once())
            ->method('post')
            ->with("/bt/cancelar_boleto", ['id_boleto' => $chargeId])
            ->willReturn($expectedResponse);

        $result = $this->boletoService->cancel($chargeId);
        $this->assertSame($expectedResponse, $result);
    }

    public function testListBoletos(): void
    {
        $filters = [
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'status' => 'paid'
        ];
        
        $expectedResponse = [
            'total' => 2,
            'page' => 1,
            'items' => [
                [
                    'id' => 'bol_123',
                    'status' => 'paid',
                    'amount' => 10000
                ],
                [
                    'id' => 'bol_456',
                    'status' => 'paid',
                    'amount' => 20000
                ]
            ]
        ];

        $this->clientMock->expects($this->once())
            ->method('get')
            ->with('/bt/consulta_boletos', $filters)
            ->willReturn($expectedResponse);

        $result = $this->boletoService->list($filters);
        $this->assertSame($expectedResponse, $result);
    }

    public function testListBoletosWithoutFilters(): void
    {
        $expectedResponse = [
            'total' => 0,
            'page' => 1,
            'items' => []
        ];

        $this->clientMock->expects($this->once())
            ->method('get')
            ->with('/bt/consulta_boletos', [])
            ->willReturn($expectedResponse);

        $result = $this->boletoService->list();
        $this->assertSame($expectedResponse, $result);
    }
}
