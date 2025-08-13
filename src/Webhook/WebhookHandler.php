 <?php
 
 namespace R2Soft\Canvi\Webhook;
 final class WebhookHandler
 {
     public function __construct(private readonly DefaultWebhookValidator $v)
     {
     }
 
     public function handle(array $s, string $raw): array
     {

         if (!$this->v->isValid($s, $raw)) return ['ok' => false];
         return ['ok' => true, 'event' => json_decode($raw, true)];
     }
 }
