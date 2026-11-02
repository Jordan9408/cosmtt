<?php

declare(strict_types=1);

/**
 * Limitation de débit persistante (fichiers), indépendante de la session / APCu.
 * Un attaquant ne peut pas réinitialiser le compteur en vidant ses cookies.
 */
final class RateLimiter
{
    public function __construct(
        private readonly string $storageDir,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
    ) {
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0750, true);
        }
    }

    public function isLocked(string $key): bool
    {
        $data = $this->read($key);

        return $data !== null && (int) ($data['lock_until'] ?? 0) > time();
    }

    public function remainingMinutes(string $key): int
    {
        $data = $this->read($key);
        $lockUntil = (int) ($data['lock_until'] ?? 0);
        if ($lockUntil <= time()) {
            return 0;
        }

        return (int) max(1, (int) ceil(($lockUntil - time()) / 60));
    }

    /**
     * Enregistre un échec. Retourne true si la clé est désormais verrouillée.
     */
    public function hit(string $key): bool
    {
        $now = time();
        $data = $this->read($key) ?? [
            'attempts'     => 0,
            'lock_until'   => 0,
            'window_start' => $now,
        ];

        if ((int) $data['lock_until'] > $now) {
            return true;
        }

        if ((int) $data['window_start'] + $this->windowSeconds < $now) {
            $data['attempts'] = 0;
            $data['window_start'] = $now;
            $data['lock_until'] = 0;
        }

        $data['attempts'] = (int) $data['attempts'] + 1;

        if ($data['attempts'] >= $this->maxAttempts) {
            $data['lock_until'] = $now + $this->windowSeconds;
        }

        $this->write($key, $data);

        return (int) $data['lock_until'] > $now;
    }

    public function clear(string $key): void
    {
        $file = $this->filePath($key);
        if (is_file($file)) {
            unlink($file);
        }
    }

    /** @return array{attempts:int,lock_until:int,window_start:int}|null */
    private function read(string $key): ?array
    {
        $file = $this->filePath($key);
        if (!is_file($file)) {
            return null;
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            return null;
        }

        try {
            flock($handle, LOCK_SH);
            $raw = stream_get_contents($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        return [
            'attempts'     => (int) ($decoded['attempts'] ?? 0),
            'lock_until'   => (int) ($decoded['lock_until'] ?? 0),
            'window_start' => (int) ($decoded['window_start'] ?? time()),
        ];
    }

    /** @param array{attempts:int,lock_until:int,window_start:int} $data */
    private function write(string $key, array $data): void
    {
        $file = $this->filePath($key);
        $handle = fopen($file, 'c+b');
        if ($handle === false) {
            error_log('[RateLimiter] Impossible d\'écrire ' . $file);
            return;
        }

        try {
            flock($handle, LOCK_EX);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($data, JSON_THROW_ON_ERROR));
            fflush($handle);
        } catch (JsonException $e) {
            error_log('[RateLimiter] JSON: ' . $e->getMessage());
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function filePath(string $key): string
    {
        return $this->storageDir . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    }
}
