<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $user_type student | faculty | staff | evaluator
 * @property int|null $academic_unit_id
 * @property int|null $program_id
 * @property string|null $recovery_email
 * @property Carbon|null $recovery_email_verified_at
 * @property string|null $recovery_mobile
 * @property Carbon|null $recovery_mobile_verified_at
 * @property string|null $theme_color
 * @property string $theme_mode
 * @property Carbon|null $email_verified_at
 * @property bool $is_active
 * @property Carbon|null $deactivated_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $password_changed_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** The five roles (CLAUDE.md §1 rule 4). */
    public const ROLE_STUDENT = 'student';

    public const ROLE_ADVISER = 'faculty_adviser';

    public const ROLE_COORDINATOR = 'research_coordinator';

    public const ROLE_URDC = 'urdc_administrator';

    public const ROLE_SYSADMIN = 'system_administrator';

    public const ROLES = [
        self::ROLE_STUDENT, self::ROLE_ADVISER, self::ROLE_COORDINATOR, self::ROLE_URDC, self::ROLE_SYSADMIN,
    ];

    /**
     * Defaults matching the migration, so a new in-memory model reads the same as one loaded from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'user_type' => 'student',
        'is_active' => false,
        'theme_mode' => 'system',
        'academic_unit_id' => null,
        'program_id' => null,
        'deactivated_at' => null,
        'recovery_email' => null,
        'recovery_email_verified_at' => null,
        'recovery_mobile' => null,
        'recovery_mobile_verified_at' => null,
        'theme_color' => null,
        'expires_at' => null,
        'last_login_at' => null,
        'password_changed_at' => null,
    ];

    /** Roles a person may pick at self-registration (§1 rule 3). */
    public const SELF_ASSIGNABLE_ROLES = [self::ROLE_STUDENT, self::ROLE_ADVISER];

    /**
     * Mass assignable. Role, is_active and deactivated_at are left out on
     * purpose: only the System administrator changes them, through explicit code.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'academic_unit_id',
        'program_id',
        'user_type',
        'theme_color',
        'theme_mode',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'recovery_email_verified_at' => 'datetime',
            'recovery_mobile_verified_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<AcademicUnit, $this> */
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** Only active, non-deactivated, non-expired accounts may sign in. */
    public function canSignIn(): bool
    {
        return $this->is_active
            && $this->deactivated_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** Case-insensitive exact match on the TUA domain (§7 M1). */
    public static function isTuaEmail(string $email): bool
    {
        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        return $domain === strtolower((string) config('tua.email_domain'));
    }
}
