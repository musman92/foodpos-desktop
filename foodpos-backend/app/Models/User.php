<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /** @var list<string> Values allowed in users.type (account / tenancy level). */
    public const ACCOUNT_TYPES = [
        'super_admin',
        'company_admin',
        'branch_manager',
        'staff',
        'waiter',
        'rider',
        'waiter_rider',
    ];

    /** @var list<string> Floor jobs that share staff-level tenancy (not admin). */
    public const STAFF_LIKE_ACCOUNT_TYPES = [
        'staff',
        'waiter',
        'rider',
        'waiter_rider',
    ];

    /** @var list<string> Waiter / rider floor account levels. */
    public const FLOOR_ACCOUNT_TYPES = [
        'waiter',
        'rider',
        'waiter_rider',
    ];

    /** @var list<string> Types that appear in the POS waiter picker. */
    public const WAITER_ACCOUNT_TYPES = [
        'waiter',
        'waiter_rider',
    ];

    /** @var list<string> Types that appear in the POS rider picker. */
    public const RIDER_ACCOUNT_TYPES = [
        'rider',
        'waiter_rider',
    ];

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'email',
        'password',
        'phone',
        'type',
        'status',
        'can_login',
        'salary',
        'balance',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'can_login' => 'boolean',
    ];

    /**
     * Get the company that owns this user.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the branch that owns this user (primary branch - for backward compatibility).
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get all branches associated with this user (many-to-many).
     */
    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'user_branches')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function employeeProfile()
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class, 'employee_id');
    }

    public function employeePayments()
    {
        return $this->hasMany(EmployeePayment::class, 'employee_id');
    }

    public function employeeAdvances()
    {
        return $this->hasMany(EmployeeAdvance::class, 'employee_id');
    }

    public function employeeLeaveRequests()
    {
        return $this->hasMany(EmployeeLeaveRequest::class, 'employee_id');
    }

    public function employeeLedgerEntries()
    {
        return $this->hasMany(EmployeeLedgerEntry::class, 'employee_id');
    }

    /**
     * Get the primary branch for this user.
     */
    public function primaryBranch()
    {
        return $this->branches()->wherePivot('is_primary', true)->first();
    }

    /**
     * Whether this user may sign in to the application.
     */
    public function canLogin(): bool
    {
        return (bool) $this->can_login;
    }

    /**
     * Check if user is super admin (by type).
     */
    public function isSuperAdmin(): bool
    {
        return $this->type === 'super_admin';
    }

    /**
     * Check if user is super user (not a tenant: no company, no branch).
     * Super users get a different layout, dashboard and sidebar.
     */
    public function isSuperUser(): bool
    {
        return $this->company_id === null && $this->branch_id === null;
    }

    /**
     * Check if user is company admin.
     */
    public function isCompanyAdmin(): bool
    {
        return $this->type === 'company_admin';
    }

    /**
     * Staff-tier account (includes waiter / rider variants).
     */
    public function isStaffLike(): bool
    {
        return in_array($this->type, self::STAFF_LIKE_ACCOUNT_TYPES, true);
    }

    public function canServeAsWaiter(): bool
    {
        return in_array($this->type, self::WAITER_ACCOUNT_TYPES, true);
    }

    public function canServeAsRider(): bool
    {
        return in_array($this->type, self::RIDER_ACCOUNT_TYPES, true);
    }

    /**
     * Human-readable account level (not the Spatie job role).
     */
    public function accountTypeLabel(): string
    {
        return match ($this->type) {
            'super_admin' => 'Super Admin',
            'company_admin' => 'Company Admin',
            'branch_manager' => 'Branch Manager',
            'staff' => 'Staff',
            'waiter' => 'Waiter',
            'rider' => 'Rider',
            'waiter_rider' => 'Waiter / Rider',
            default => ucfirst(str_replace('_', ' ', (string) $this->type)),
        };
    }

    /**
     * Primary Spatie role name (Cashier, Manager, etc.).
     */
    public function primaryRoleName(): ?string
    {
        $this->loadMissing('roles');

        return $this->roles->first()?->name;
    }

    /**
     * Check if user can access multiple branches.
     */
    public function canAccessMultipleBranches(): bool
    {
        return in_array($this->type, ['super_admin', 'company_admin']);
    }

    /**
     * Spatie can(), with bypass for platform-wide accounts (see OrderManagementController, sidebar).
     */
    public function hasAppPermission(string $permission): bool
    {
        if ($this->isSuperAdmin() || $this->isSuperUser()) {
            return true;
        }

        return $this->can($permission);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function hasAnyAppPermission(array $permissions): bool
    {
        if ($this->isSuperAdmin() || $this->isSuperUser()) {
            return true;
        }
        foreach ($permissions as $permission) {
            if ($this->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
