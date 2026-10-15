<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class SocialAccount extends Model
{
    protected $fillable = [
        'user_id',
        'platform',
        'external_account_id',
        'username',
        'status',
        'access_token_encrypted',
        'refresh_token_encrypted',
        'granted_scopes',
        'token_expires_at',
        'last_verified_at',
    ];

    protected $hidden = [
        'access_token_encrypted',
        'refresh_token_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'granted_scopes' => 'array',
            'token_expires_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function setAccessToken(string $token): void
    {
        $this->access_token_encrypted = Crypt::encryptString($token);
    }

    public function getAccessToken(): ?string
    {
        if (! filled($this->access_token_encrypted)) {
            return null;
        }

        return Crypt::decryptString($this->access_token_encrypted);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected'
            && $this->getAccessToken() !== null
            && (
                $this->token_expires_at === null
                || $this->token_expires_at->isFuture()
            );
    }
}
