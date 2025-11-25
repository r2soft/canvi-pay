<?php

declare(strict_types=1);

namespace R2Soft\Canvi\Services;

use PDO;
use Psr\Log\LoggerInterface;
use R2Soft\Canvi\Contracts\TokenStorageInterface;
use R2Soft\Canvi\Exceptions\TokenStorageException;

/**
 * Armazena tokens de acesso em banco de dados.
 */
final class TokenStorage implements TokenStorageInterface
{
    private const TOKEN_TABLE = 'canvi_tokens';
    private const TOKEN_EXPIRATION_MARGIN = 300; // 5 minutos de margem

    private PDO $pdo;
    private string $identifier;
    private string $walletId;
    private string $table;
    private ?LoggerInterface $logger;

    public function __construct(PDO $pdo, string $identifier = 'default', string $walletId = 'default', ?string $schema = null, ?LoggerInterface $logger = null)
    {
        if ($identifier === '') {
            throw new \InvalidArgumentException('O identificador não pode ser vazio');
        }

        if ($walletId === '') {
            throw new \InvalidArgumentException('O wallet_id não pode ser vazio');
        }

        $this->identifier = $identifier;
        $this->walletId = $walletId;
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->table = $this->resolveTableName($schema, self::TOKEN_TABLE);

        $this->configurePdo();
        $this->ensureTableExists();
    }

    /**
     * Cria storage a partir das variáveis de ambiente BD_PJ_*.
     */
    public static function fromEnv(?string $identifier = 'default', ?LoggerInterface $logger = null, ?string $walletId = 'default'): self
    {
        $required = [
            'BD_PJ_HOST',
            'BD_PJ_PORT',
            'BD_PJ_DATABASE',
            'BD_PJ_LOGIN',
            'BD_PJ_PASSWORD',
        ];

        foreach ($required as $key) {
            if (!getenv($key)) {
                throw new TokenStorageException("Variável de ambiente obrigatória ausente: {$key}");
            }
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            getenv('BD_PJ_HOST'),
            getenv('BD_PJ_PORT'),
            getenv('BD_PJ_DATABASE')
        );

        try {
            $pdo = new PDO(
                $dsn,
                getenv('BD_PJ_LOGIN'),
                getenv('BD_PJ_PASSWORD'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (\PDOException $e) {
            throw new TokenStorageException('Erro ao conectar ao banco de dados: ' . $e->getMessage(), 0, $e);
        }

        $schema = getenv('BD_PJ_SCHEMA') ?: null;

        return new self($pdo, $identifier ?? 'default', $walletId ?? 'default', $schema, $logger);
    }

    private function resolveTableName(?string $schema, string $table): string
    {
        $this->assertValidName($table, 'nome da tabela');
        if ($schema === null || $schema === '') {
            return $table;
        }

        $this->assertValidName($schema, 'schema');
        return $schema . '.' . $table;
    }

    private function assertValidName(string $value, string $label): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            throw new TokenStorageException("{$label} inválido: {$value}");
        }
    }

    private function configurePdo(): void
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    private function ensureTableExists(): void
    {
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS %s (
                identifier VARCHAR(255) NOT NULL,
                wallet_id VARCHAR(255) NOT NULL,
                token TEXT NOT NULL,
                expires_at BIGINT NOT NULL,
                created_at BIGINT NOT NULL,
                PRIMARY KEY (wallet_id)
            )',
            $this->table
        );

        try {
            $this->pdo->exec($sql);
        } catch (\Throwable $e) {
            throw new TokenStorageException('Não foi possível garantir a tabela de tokens: ' . $e->getMessage(), 0, $e);
        }
    }

    public function loadToken(): ?array
    {
        try {
            $stmt = $this->pdo->prepare(
                sprintf('SELECT token, expires_at, created_at, identifier FROM %s WHERE wallet_id = :wallet_id LIMIT 1', $this->table)
            );
            $stmt->execute([
                ':wallet_id' => $this->walletId,
            ]);
            $row = $stmt->fetch();

            if ($row === false) {
                return null;
            }

            $expiresAt = (int) ($row['expires_at'] ?? 0);
            if (time() > ($expiresAt - self::TOKEN_EXPIRATION_MARGIN)) {
                $this->clearToken();
                return null;
            }

            return [
                'token' => (string) $row['token'],
                'expires_at' => $expiresAt,
                'created_at' => (int) ($row['created_at'] ?? 0),
            ];
        } catch (\Throwable $e) {
            $this->logger?->error('Falha ao carregar token do banco', ['error' => $e->getMessage()]);
            throw new TokenStorageException('Falha ao carregar token do banco: ' . $e->getMessage(), 0, $e);
        }
    }

    public function saveToken(string $token, int $expiresIn): void
    {
        if ($token === '') {
            throw new \InvalidArgumentException('O token não pode ser vazio');
        }

        if ($expiresIn <= 0) {
            throw new \InvalidArgumentException('O tempo de expiração deve ser maior que zero');
        }

        $now = time();
        $expiresAt = $now + $expiresIn;

        try {
            $this->pdo->beginTransaction();

            $delete = $this->pdo->prepare(sprintf('DELETE FROM %s WHERE wallet_id = :wallet_id', $this->table));
            $delete->execute([
                ':wallet_id' => $this->walletId,
            ]);

            $insert = $this->pdo->prepare(
                sprintf(
                    'INSERT INTO %s (identifier, wallet_id, token, expires_at, created_at) VALUES (:identifier, :wallet_id, :token, :expires_at, :created_at)',
                    $this->table
                )
            );

            $insert->execute([
                ':identifier' => $this->identifier,
                ':wallet_id' => $this->walletId,
                ':token' => $token,
                ':expires_at' => $expiresAt,
                ':created_at' => $now,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logger?->error('Falha ao salvar token no banco', ['error' => $e->getMessage()]);
            throw new TokenStorageException('Falha ao salvar token no banco: ' . $e->getMessage(), 0, $e);
        }
    }

    public function clearToken(): void
    {
        try {
            $stmt = $this->pdo->prepare(sprintf('DELETE FROM %s WHERE wallet_id = :wallet_id', $this->table));
            $stmt->execute([
                ':wallet_id' => $this->walletId,
            ]);
        } catch (\Throwable $e) {
            $this->logger?->error('Falha ao limpar token do banco', ['error' => $e->getMessage()]);
            throw new TokenStorageException('Falha ao limpar token do banco: ' . $e->getMessage(), 0, $e);
        }
    }
}
