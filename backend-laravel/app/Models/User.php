<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Permissions\V1\Abilities;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

#[Fillable(['clinic_id', 'branch_id', 'name', 'email', 'password', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
        ];
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class, 'clinic_id', 'clinic_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'branch_id');
    }

    public function dentist()
    {
        return $this->hasOne(Dentist::class, 'user_id');
    }

    public function patient()
    {
        return $this->hasOne(Patient::class, 'user_id');
    }

    /**
     * The value stored inside the token to identify the user (the user id).
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Extra data stored in the token payload: the user's abilities.
     * They are written into the JWT every time the user logs in.
     */
    public function getJWTCustomClaims()
    {
        return [
            'abilities' => Abilities::getAbilities($this),
        ];
    }

    /**
     * Checks an ability stored in the current JWT, for example 'appointment:update'.
     * Sanctum's own tokenCan() does not read JWT claims, so we implement it here.
     */
    public function tokenCan(string $ability): bool
    {
        try {
            // Read the "abilities" claim of the token sent with this request
            $abilities = auth('api')->payload()->get('abilities') ?? [];
        } catch (\Throwable $e) {
            // No token, or an unreadable one: no abilities
            return false;
        }

        return in_array($ability, $abilities, true);
    }
}