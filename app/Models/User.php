<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'username',
        'email',
        'password',
        'phone',
        'branch_id',
        'section_id',
        'department_id',
        'role',
        'role_id',
        'is_active',
        'email_verified_at',
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * The branch this user belongs to.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * The section this user belongs to.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * The department this user belongs to.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The id of the branch the user is currently acting within.
     *
     * This is the single canonical "active branch" accessor. Administrators
     * may switch branches (stored in the session by BranchSwitchController);
     * everyone else is fixed to their assigned branch. All branch-scoped
     * queries and UI should read this rather than branch_id directly.
     */
    public function activeBranchId(): ?int
    {
        if ($this->hasRole('Administrator')) {
            return session('active_branch_id', $this->branch_id);
        }

        return $this->branch_id;
    }

    /**
     * The Branch model the user is currently acting within.
     */
    public function activeBranch(): ?Branch
    {
        $id = $this->activeBranchId();

        return $id ? Branch::find($id) : null;
    }
}
