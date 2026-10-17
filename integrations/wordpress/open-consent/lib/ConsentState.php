<?php

declare(strict_types=1);

namespace SeoUltra\OpenConsent;

/**
 * A visitor's consent as read from the browser's consent record.
 *
 * Validation matches packages/web/src/consent-state.js: anything missing,
 * corrupt, expired, from another policy version, or missing a configured
 * category is NOT consent. The record lives in the visitor's browser and can
 * be edited by them — use it to decide what to render, never to authorize
 * anything security-sensitive.
 */
final class ConsentState
{
    public const METHODS = ['accept-all', 'reject-all', 'custom', 'withdraw'];

    /**
     * @param array<string, bool> $categories effective categories (implied when not valid)
     * @param array<string, mixed>|null $record the validated record, if any
     */
    private function __construct(
        private readonly bool $valid,
        private readonly string $reason,
        private readonly array $categories,
        private readonly ?array $record,
    ) {
    }

    public static function fromJson(?string $raw, Config $config, ?int $now = null): self
    {
        if ($raw === null || $raw === '') {
            return self::none($config, 'missing');
        }
        try {
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::none($config, 'corrupt');
        }
        return self::fromArray($data, $config, $now);
    }

    public static function fromArray(mixed $rec, Config $config, ?int $now = null): self
    {
        $t = $now ?? time();
        if (!is_array($rec) || array_is_list($rec) || !is_int($rec['schema'] ?? null)) {
            return self::none($config, 'corrupt');
        }
        if ($rec['schema'] !== OpenConsent::SCHEMA_VERSION) {
            // No older schema exists yet; when one does, migrate it here like the JS engine.
            return self::none($config, 'schema');
        }
        if (!is_array($rec['categories'] ?? null) || ($rec['categories'] !== [] && array_is_list($rec['categories']))) {
            return self::none($config, 'corrupt');
        }
        $cats = [];
        $complete = true;
        foreach ($config->categoryNames() as $n) {
            if (!array_key_exists($n, $rec['categories'])) {
                $complete = false;
                continue;
            }
            if (!is_bool($rec['categories'][$n])) {
                return self::none($config, 'corrupt');
            }
            $cats[$n] = $rec['categories'][$n];
        }
        foreach (['createdAt', 'updatedAt', 'expiresAt'] as $k) {
            if (!is_int($rec[$k] ?? null) && !is_float($rec[$k] ?? null)) {
                return self::none($config, 'corrupt');
            }
        }
        if (!in_array($rec['method'] ?? null, self::METHODS, true) || !is_string($rec['policy'] ?? null)) {
            return self::none($config, 'corrupt');
        }
        if ($rec['policy'] !== $config->policyVersion()) {
            return self::none($config, 'policy');
        }
        if ($rec['expiresAt'] <= $t) {
            return self::none($config, 'expired');
        }
        if (!$complete) {
            return self::none($config, 'categories');
        }
        if (($cats['necessary'] ?? false) !== true) {
            return self::none($config, 'corrupt');
        }
        $record = [
            'schema' => OpenConsent::SCHEMA_VERSION,
            'policy' => $rec['policy'],
            'profile' => is_string($rec['profile'] ?? null) ? $rec['profile'] : $config->profile(),
            'method' => $rec['method'],
            'categories' => $cats,
            'createdAt' => (int) $rec['createdAt'],
            'updatedAt' => (int) $rec['updatedAt'],
            'expiresAt' => (int) $rec['expiresAt'],
        ];
        return new self(true, 'ok', $cats, $record);
    }

    /**
     * Effective categories when there is no valid record, per policy profile.
     * Server side cannot see Global Privacy Control's JS property, but it can
     * see the Sec-GPC request header — pass it in.
     */
    public static function none(Config $config, string $reason, bool $gpc = false): self
    {
        $cats = [];
        $optOut = $config->profile() === 'opt-out';
        $gpc = $gpc && (bool) $config->get('gpc');
        foreach ($config->categoryNames() as $n) {
            if ($config->isRequired($n)) {
                $cats[$n] = true;
            } elseif ($optOut) {
                $cats[$n] = !($gpc && $n === 'marketing');
            } else {
                $cats[$n] = false;
            }
        }
        return new self(false, $reason, $cats, null);
    }

    /**
     * Build a new record for a choice (for server-rendered consent flows and tests).
     *
     * @param array<string, mixed> $choice category => bool; only `true` grants
     */
    public static function create(Config $config, array $choice, string $method, ?int $now = null): self
    {
        if (!in_array($method, self::METHODS, true)) {
            throw new \InvalidArgumentException('Unknown consent method: ' . $method);
        }
        $t = $now ?? time();
        $cats = [];
        foreach ($config->categoryNames() as $n) {
            $cats[$n] = $config->isRequired($n) ? true : (($choice[$n] ?? false) === true);
        }
        $record = [
            'schema' => OpenConsent::SCHEMA_VERSION,
            'policy' => $config->policyVersion(),
            'profile' => $config->profile(),
            'method' => $method,
            'categories' => $cats,
            'createdAt' => $t,
            'updatedAt' => $t,
            'expiresAt' => $t + $config->cookieDays() * 86400,
        ];
        return new self(true, 'ok', $cats, $record);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    /** True when the visitor has made a (still valid) choice. */
    public function isDecided(): bool
    {
        return $this->valid;
    }

    /** 'ok', 'missing', 'corrupt', 'schema', 'policy', 'expired', 'categories' */
    public function reason(): string
    {
        return $this->reason;
    }

    public function allows(string $category): bool
    {
        return ($this->categories[$category] ?? false) === true;
    }

    /** @return array<string, bool> */
    public function categories(): array
    {
        return $this->categories;
    }

    public function method(): ?string
    {
        return $this->record['method'] ?? null;
    }

    /** @return array<string, mixed>|null */
    public function record(): ?array
    {
        return $this->record;
    }

    public function toJson(): string
    {
        if ($this->record === null) {
            throw new \LogicException('No consent record to serialize');
        }
        return json_encode($this->record, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
