<?php

namespace App\Models;

use App\Enums\YouTubeMusicConnectionStatus;
use Database\Factories\YouTubeMusicConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The owner's YouTube Music connection (youtube-music-connection, design D5). The tokens are encrypted at rest and
 * hidden from serialisation, so an accidental toArray() never sends them to the browser.
 */
class YouTubeMusicConnection extends Model
{
    /** @use HasFactory<YouTubeMusicConnectionFactory> */
    use HasFactory;

    protected $table = 'youtube_music_connections';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'connection_id',
        'channel_id',
        'channel_title',
        'scopes',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'authorized_at',
        'status',
        'config_error_at',
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
            'config_error_at' => 'datetime',
            'status' => YouTubeMusicConnectionStatus::class,
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
