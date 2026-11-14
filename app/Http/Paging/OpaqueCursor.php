<?php

namespace App\Http\Paging;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * An opaque paging cursor (spotify-library-tracks, design D3). It encrypts a scope and an offset with the app key, so
 * the browser can neither read nor alter it, and a cursor only works for the list it was made for. The search, the
 * playlists and Liked Songs each use their own scope.
 */
final class OpaqueCursor
{
    public static function encode(string $scope, int $offset): string
    {
        return Crypt::encryptString(json_encode(['s' => $scope, 'o' => $offset], JSON_THROW_ON_ERROR));
    }

    /**
     * The cursor's offset, or null when it does not decrypt, belongs to another scope, or carries an offset outside 0
     * to $maxOffset.
     */
    public static function decode(string $cursor, string $scope, int $maxOffset): ?int
    {
        try {
            $payload = json_decode(Crypt::decryptString($cursor), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($payload)
            || ! is_string($payload['s'] ?? null)
            || ! hash_equals($scope, $payload['s'])
            || ! is_int($payload['o'] ?? null)
            || $payload['o'] < 0
            || $payload['o'] > $maxOffset) {
            return null;
        }

        return $payload['o'];
    }
}
