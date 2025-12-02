<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'whatsapp_session_id',
        'name',
        'email',
        'password',
        'password_changed_at',
        'work_schedule',
        'languages',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_enabled',
        'two_factor_confirmed_at',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'password_changed_at' => 'datetime',
            'work_schedule' => 'array',
            'languages' => 'array',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function setPasswordAttribute($value)
    {
        if (empty($value)) {
            return;
        }

        $this->attributes['password'] = bcrypt($value);
        $this->attributes['password_changed_at'] = now();
    }

    public function needsPasswordChange(): bool
    {
        if (!$this->password_changed_at) {
            return true;
        }

        return $this->password_changed_at->diffInDays(now()) >= 90;
    }

    public function updatePasswordChangedAt()
    {
        $this->update(['password_changed_at' => now()]);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function whatsappSession()
    {
        return $this->belongsTo(WhatsappSession::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission', 'role_id', 'permission_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Generate new recovery codes for two-factor authentication
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(8))), 0, 10));
        }
        
        $this->two_factor_recovery_codes = encrypt(json_encode($codes));
        $this->save();
        
        return $codes;
    }

    /**
     * Check if a recovery code is valid
     */
    public function validateRecoveryCode(string $code): bool
    {
        if (!$this->two_factor_recovery_codes) {
            return false;
        }

        $codes = json_decode(decrypt($this->two_factor_recovery_codes), true);
        
        if (in_array(strtoupper($code), $codes)) {
            // Remove used recovery code
            $codes = array_diff($codes, [strtoupper($code)]);
            $this->two_factor_recovery_codes = encrypt(json_encode(array_values($codes)));
            $this->save();
            
            return true;
        }

        return false;
    }

    /**
     * Get decrypted recovery codes
     */
    public function getRecoveryCodes(): array
    {
        if (!$this->two_factor_recovery_codes) {
            return [];
        }

        return json_decode(decrypt($this->two_factor_recovery_codes), true);
    }
}
