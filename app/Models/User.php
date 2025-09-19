<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'organization_id',
        'pending_approval',
        'approved_at',
        'approved_by',
        'is_super_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'pending_approval' => 'boolean',
        'approved_at' => 'datetime',
        'is_super_admin' => 'boolean',
        ];
    }

    /**
     * Get the organization that the user belongs to.
     */
    public function organization(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the groups that the user belongs to.
     */
    public function groups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withTimestamps()->withPivot('joined_at');
    }

    /**
     * Get the roles that the user has.
     */
    public function roles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps()->withPivot(['assigned_at', 'assigned_by']);
    }

    /**
     * Get the user who approved this user.
     */
    public function approvedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the projects for the user.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Check if user is approved.
     */
    public function isApproved(): bool
    {
        return !$this->pending_approval;
    }

    /**
     * Get the email domain for the user.
     */
    public function getEmailDomain(): string
    {
        return substr(strrchr($this->email, "@"), 1);
    }

    /**
     * Get the default group for the user's organization.
     */
    public function getDefaultGroup()
    {
        return $this->organization?->defaultGroup();
    }

    /**
     * Get all groups the user belongs to within their organization.
     */
    public function getOrganizationGroups()
    {
        if (!$this->organization_id) {
            return collect();
        }

        return $this->groups()->whereHas('organization', function ($query) {
            $query->where('id', $this->organization_id);
        })->get();
    }

    /**
     * Check if user belongs to a specific group.
     */
    public function belongsToGroup(int $groupId): bool
    {
        return $this->groups()->where('groups.id', $groupId)->exists();
    }

    /**
     * Add user to a group.
     */
    public function joinGroup(Group $group): void
    {
        if (!$this->belongsToGroup($group->id)) {
            $this->groups()->attach($group->id, ['joined_at' => now()]);
        }
    }

    /**
     * Remove user from a group (except default group).
     */
    public function leaveGroup(Group $group): bool
    {
        if ($group->is_default) {
            return false; // Cannot leave default group
        }

        $this->groups()->detach($group->id);
        return true;
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles()->whereIn('name', $roleNames)->exists();
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->roles()->get()->some(function ($role) use ($permission) {
            return $role->hasPermission($permission);
        });
    }

    /**
     * Check if user is admin in their organization.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(Role $role, ?User $assignedBy = null): void
    {
        if (!$this->hasRole($role->name)) {
            $this->roles()->attach($role->id, [
                'assigned_at' => now(),
                'assigned_by' => $assignedBy?->id,
            ]);
        }
    }

    /**
     * Remove a role from the user.
     */
    public function removeRole(Role $role): void
    {
        $this->roles()->detach($role->id);
    }

    /**
     * Get all permissions for the user.
     */
    public function getAllPermissions(): array
    {
        return $this->roles()->get()
            ->flatMap(fn($role) => $role->permissions ?? [])
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Check if the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /**
     * Make this user a super admin (can only be done by another super admin).
     */
    public function makeSuperAdmin(): void
    {
        $this->update(['is_super_admin' => true]);
    }

    /**
     * Remove super admin privileges from this user.
     */
    public function removeSuperAdmin(): void
    {
        $this->update(['is_super_admin' => false]);
    }

    /**
     * Check if user can manage organizations (super admin privilege).
     */
    public function canManageOrganizations(): bool
    {
        return $this->is_super_admin;
    }

    /**
     * Check if user can login as other users (super admin privilege).
     */
    public function canLoginAsOtherUsers(): bool
    {
        return $this->is_super_admin;
    }

    /**
     * Check if user can assign super admin role (super admin privilege).
     */
    public function canAssignSuperAdmin(): bool
    {
        return $this->is_super_admin;
    }

    /**
     * Get all users that this user can manage.
     */
    public function getManagedUsers()
    {
        if ($this->is_super_admin) {
            // Super admins can manage all users
            return User::query();
        }

        if ($this->isAdmin()) {
            // Regular admins can only manage users in their organization
            return User::where('organization_id', $this->organization_id)
                ->where('is_super_admin', false) // Cannot manage super admins
                ->where('id', '!=', $this->id); // Cannot manage themselves
        }

        // Regular users cannot manage anyone
        return User::whereRaw('1 = 0'); // Empty query
    }
}
