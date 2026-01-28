<?php
// src/Cache.php
use Predis\Client;

class Cache {
    private Client $r;

    public function __construct(Client $r) {
        $this->r = $r;
    }

    /** Returns cached JSON value or computes it, stores it with TTL, and returns it (decoded). */
    public function remember(string $key, int $ttlSeconds, callable $build) {
        $cached = $this->r->get($key);
        if ($cached !== null) {
            return json_decode($cached, true);
        }
        $value = $build();
        $this->r->setex($key, $ttlSeconds, json_encode($value));
        return $value;
    }

    /** Put a value (JSON) with optional TTL. */
    public function put(string $key, $value, ?int $ttlSeconds = null): void {
        $payload = json_encode($value);
        if ($ttlSeconds !== null) {
            $this->r->setex($key, $ttlSeconds, $payload);
        } else {
            $this->r->set($key, $payload);
        }
    }

    /** Get and decode JSON value, or null if missing. */
    public function get(string $key) {
        $val = $this->r->get($key);
        return $val !== null ? json_decode($val, true) : null;
    }

    /** Delete one or more keys. */
    public function forget(string ...$keys): void {
        if (!empty($keys)) {
            $this->r->del($keys);
        }
    }
}