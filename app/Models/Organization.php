<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    /** @use HasFactory<\Database\Factories\OrganizationFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'domain',
        'address',
        'creator_id',
        'is_default',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Get the creator of the organization.
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * Get the groups for the organization.
     */
    public function groups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Group::class);
    }

    /**
     * Get the roles for the organization.
     */
    public function roles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Get the users for the organization.
     */
    public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the default group for the organization.
     */
    public function defaultGroup()
    {
        return $this->groups()->where('is_default', true)->first();
    }

    /**
     * Get the default 'None' organization.
     */
    public static function getDefault()
    {
        return static::where('is_default', true)->first();
    }

    /**
     * Create a default group for the organization.
     */
    public function createDefaultGroup(): Group
    {
        return $this->groups()->create([
            'name' => 'Everyone',
            'description' => 'Default group for all members of '.$this->name,
            'is_default' => true,
        ]);
    }

    /**
     * Create default roles for the organization.
     */
    public function createDefaultRoles(): array
    {
        $adminRole = $this->roles()->create([
            'name' => 'admin',
            'display_name' => 'Administrator',
            'description' => 'Organization administrator with full management rights',
            'permissions' => Role::getAdminPermissions(),
        ]);

        $userRole = $this->roles()->create([
            'name' => 'user',
            'display_name' => 'User',
            'description' => 'Standard organization member',
            'permissions' => Role::getUserPermissions(),
        ]);

        return ['admin' => $adminRole, 'user' => $userRole];
    }

    /**
     * Get admin role for the organization.
     */
    public function getAdminRole()
    {
        return $this->roles()->where('name', 'admin')->first();
    }

    /**
     * Get user role for the organization.
     */
    public function getUserRole()
    {
        return $this->roles()->where('name', 'user')->first();
    }
}
