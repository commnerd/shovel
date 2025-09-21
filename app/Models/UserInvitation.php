<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UserInvitation extends Model
{
    use HasFactory;
    protected $fillable = [
        'email',
        'token',
        'organization_id',
        'invited_by',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return !is_null($this->accepted_at);
    }

    public function isPending(): bool
    {
        return !$this->isExpired() && !$this->isAccepted();
    }

    public static function generateToken(): string
    {
        return Str::random(60);
    }

    public static function createInvitation(string $email, ?int $organizationId, int $invitedBy): self
    {
        return self::create([
            'email' => $email,
            'token' => self::generateToken(),
            'organization_id' => $organizationId,
            'invited_by' => $invitedBy,
            'expires_at' => Carbon::now()->addDays(7), // 7 days expiry
        ]);
    }

    public function accept(): void
    {
        $this->update(['accepted_at' => Carbon::now()]);
    }
}
