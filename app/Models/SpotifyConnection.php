<?php

namespace App\Models;

use App\Enums\SpotifyConnectionStatus;
use Database\Factories\SpotifyConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The owner's Spotify connection (spotify-connection, design D3). The tokens are encrypted at rest and hidden
 * from serialisation, so an accidental toArray() never sends them to the browser.
 */
class SpotifyConnection extends Model
{
    /** @use HasFactory<SpotifyConnectionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'connection_id',
        'spotify_user_id',
        'display_name',
        'scopes',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'authorized_at',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'authorized_at' => 'datetime',
            'status' => SpotifyConnectionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
