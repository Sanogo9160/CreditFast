<?php

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'first_name',
        'last_name',
        'phone',
        'email',
        'password',
        'status',
        'profile_photo_path',
        'agency_code',
        'zone_codes',
        'available',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'profile_photo_path',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'zone_codes' => 'array',
            'available' => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function client(): HasOne
    {
        // Un user ne devrait avoir qu’une fiche client ; en cas de doublon
        // (inscription puis seed atelier), prendre la plus récente (ex. DEMO-*).
        return $this->hasOne(Client::class)->latestOfMany();
    }

    public function userNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function hasRole(RoleName|string $role): bool
    {
        $roleName = $role instanceof RoleName ? $role->value : $role;

        return $this->role?->name === $roleName;
    }

    public function hasAnyRole(RoleName|string ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(
            RoleName::Admin,
            RoleName::CreditAgent,
            RoleName::Analyst,
            RoleName::CommitteeMember,
        );
    }
}
