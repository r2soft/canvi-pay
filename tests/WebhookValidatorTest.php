<?php
use PHPUnit\Framework\TestCase;
use R2Soft\Canvi\Webhook\DefaultWebhookValidator;

final class WebhookValidatorTest extends TestCase
{
    public function testValidHmac(): void
    {
        $secret = 'test_secret';
        $body = '{"event":"ok"}';
        $t = (string) time();
        $sig = hash_hmac('sha256', $t . '.' . $body, $secret);

        $validator = new DefaultWebhookValidator(secret: $secret);
        $server = ['HTTP_X_CANVI_SIGNATURE' => "t={$t}, v1={$sig}"];

        self::assertTrue($validator->isValid($server, $body));
    }

    public function testInvalidHmac(): void
    {
        $secret = 'test_secret';
        $body = '{"event":"ok"}';
        $t = (string) time();
        // assinatura calculada com segredo incorreto
        $sig = hash_hmac('sha256', $t . '.' . $body, 'wrong_secret');

        $validator = new DefaultWebhookValidator(secret: $secret);
        $server = ['HTTP_X_CANVI_SIGNATURE' => "t={$t}, v1={$sig}"];

        self::assertFalse($validator->isValid($server, $body));
    }

    public function testTimestampOutOfTolerance(): void
    {
        $secret = 'test_secret';
        $body = '{"event":"ok"}';
        // timestamp antigo, fora da tolerância padrão de 300s
        $t = (string) (time() - 1000);
        $sig = hash_hmac('sha256', $t . '.' . $body, $secret);

        $validator = new DefaultWebhookValidator(secret: $secret);
        $server = ['HTTP_X_CANVI_SIGNATURE' => "t={$t}, v1={$sig}"];

        self::assertFalse($validator->isValid($server, $body));
    }

    public function testValidPublicKey(): void
    {
        $body = '{"event":"ok"}';
        $t = (string) time();

        $keyResource = openssl_pkey_new(['private_key_bits' => 2048]);
        self::assertNotFalse($keyResource);
        openssl_pkey_export($keyResource, $privateKey);
        $details = openssl_pkey_get_details($keyResource);
        $publicKey = $details['key'];

        $data = $t . '.' . $body;
        openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $sig = base64_encode($signature);

        $validator = new DefaultWebhookValidator(publicKey: $publicKey);
        $server = ['HTTP_X_CANVI_SIGNATURE' => "t={$t}, sig={$sig}"];

        self::assertTrue($validator->isValid($server, $body));
    }
}
