<?php

namespace App\YouTubeMusic;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * The single waiting attempt, kept in the cache under one key for the single owner (youtube-music-connection,
 * design D2). A new attempt overwrites the previous one, so its device code can never be used again. The device
 * code is encrypted, because the cache uses the database driver.
 */
class ConnectionAttempts
{
    public const KEY = 'youtube-music:attempt';

    /**
     * The lock that serialises the polls of an attempt (design D4).
     */
    public const LOCK_KEY = 'youtube-music:attempt-poll';

    /**
     * How long the entry outlives the code, so a status request after expires_at can still answer "expired".
     */
    private const GRACE_SECONDS = 300;

    /**
     * Store a new attempt for this device code, replacing any previous one. The first poll is due at once.
     */
    public function start(DeviceCode $code): ConnectionAttempt
    {
        $now = now()->getTimestamp();

        $attempt = new ConnectionAttempt(
            (string) Str::uuid(),
            $code->deviceCode,
            $code->userCode,
            $code->verificationUrl,
            $code->interval,
            $now + $code->expiresIn,
            $now,
        );

        $this->save($attempt);

        return $attempt;
    }

    /**
     * The waiting attempt, or null when there is none or its entry no longer decrypts.
     */
    public function current(): ?ConnectionAttempt
    {
        $entry = Cache::get(self::KEY);

        if (! is_array($entry)) {
            return null;
        }

        try {
            $deviceCode = Crypt::decryptString((string) ($entry['device_code'] ?? ''));
        } catch (DecryptException) {
            Cache::forget(self::KEY);

            return null;
        }

        return new ConnectionAttempt(
            (string) $entry['id'],
            $deviceCode,
            (string) $entry['user_code'],
            (string) $entry['verification_url'],
            (int) $entry['interval'],
            (int) $entry['expires_at'],
            (int) $entry['next_poll_at'],
        );
    }

    public function save(ConnectionAttempt $attempt): void
    {
        $ttl = max(1, $attempt->expiresAt + self::GRACE_SECONDS - now()->getTimestamp());

        Cache::put(self::KEY, [
            'id' => $attempt->id,
            'device_code' => Crypt::encryptString($attempt->deviceCode),
            'user_code' => $attempt->userCode,
            'verification_url' => $attempt->verificationUrl,
            'interval' => $attempt->interval,
            'expires_at' => $attempt->expiresAt,
            'next_poll_at' => $attempt->nextPollAt,
        ], $ttl);
    }

    /**
     * End the attempt with this identifier. An attempt that replaced it meanwhile is left alone.
     */
    public function end(string $id): void
    {
        if ($this->current()?->id === $id) {
            Cache::forget(self::KEY);
        }
    }
}
