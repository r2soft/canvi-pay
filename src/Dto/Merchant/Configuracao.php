<?php
declare(strict_types=1);

Namespace R2Soft\Canvi\Dto\Merchant;

use App\Exception\DomainValidationException;

final class Configuracao
{
    /**
     * @param string[] $ip
     */
    public function __construct(
        public bool   $validar_ip,
        public array  $ip = [],
    ) {
        $this->validate();
    }

    public static function fromArray(array $a): self
    {
        return new self(
            validar_ip: (bool)($a['validar_ip'] ?? false),
            ip:         array_values((array)($a['ip'] ?? [])),
        );
    }

    public function toArray(): array
    {
        return [
            'validar_ip' => $this->validar_ip,
            'ip'         => $this->ip,
        ];
    }

    private function validate(): void
    {
        $errors = [];

        if ($this->validar_ip) {
            if ($this->ip === []){
                $errors['configuracao.ip'] = 'Lista de IPs é obrigatória quando validar_ip=true.';
            } else {
                foreach ($this->ip as $idx => $ip) {
                    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $errors["configuracao.ip[$idx]"] = 'IPv4 inválido.';
                    }
                }
            }
        }

        if ($errors) {
            throw DomainValidationException::fromArray($errors);
        }
    }
}
