<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    /** @use HasFactory<\Database\Factories\RoleFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'organization_id',
        'permissions',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * Get the organization that owns the role.
     */
    public function organization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the users that have this role.
     */
    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps()->withPivot(['assigned_at', 'assigned_by']);
    }

    /**
     * Check if role has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Get default permissions for admin role.
     */
    public static function getAdminPermissions(): array
    {
        return [
            'manage_users',
            'manage_groups',
            'manage_roles',
            'approve_users',
            'view_all_projects',
            'manage_organization',
        ];
    }

    /**
     * Get default permissions for user role.
     */
    public static function getUserPermissions(): array
    {
        return [
            'create_projects',
            'view_own_projects',
            'manage_own_projects',
        ];
    }
}
