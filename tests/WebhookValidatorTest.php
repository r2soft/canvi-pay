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
}
