<?php

namespace R2Soft\Canvi\Webhook;
final class WebhookHandler
{
    public function __construct(private readonly DefaultWebhookValidator $v)
    {
    }

    public function handle(array $s, string $raw): array
    {
        if (!$this->v->isValidFromServer($s, $raw)) return ['ok' => false];
        $event = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) return ['ok' => false];

        return ['ok' => true, 'event' => $event];
    }
}