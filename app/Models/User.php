<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'oauth_id',
        'oauth_provider',
        'error_sound',
        'daily_goal_chars',
        'auto_advance',
        'hifz_daily_new',
        'reciter',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
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
            'error_sound' => 'boolean',
            'auto_advance' => 'boolean',
            'last_practiced_on' => 'date',
            'streak_grace_used_on' => 'date',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Get the user's per-day practice activity.
     */
    public function dailyActivity(): HasMany
    {
        return $this->hasMany(DailyActivity::class);
    }

    /**
     * Get the user's per-ayah hifz review state.
     */
    public function hifzProgress(): HasMany
    {
        return $this->hasMany(UserAyahProgress::class);
    }

    /**
     * Get the user's surah completion certificates.
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Determine whether the user has super-admin access.
     */
    public function isSuperAdmin(): bool
    {
        return in_array($this->email, config('admin.super_admins', []), true);
    }

    /**
     * Get all of the typing tests for the User.
     */
    public function tests(): HasMany
    {
        return $this->hasMany(Test::class);
    }

    /**
     * Get all of the user's typing-race participations.
     *
     * @return HasMany<RaceParticipant, $this>
     */
    public function raceParticipations(): HasMany
    {
        return $this->hasMany(RaceParticipant::class);
    }

    /**
     * Get the user's preferences.
     */
    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    /**
     * Get all of the results for the User through the tests.
     */
    public function results(): HasManyThrough
    {
        return $this->hasManyThrough(Result::class, Test::class);
    }

    /**
     * Get the badges awarded to the user.
     */
    public function badges()
    {
        return $this->belongsToMany(Badge::class, 'user_badges')
            ->withPivot('awarded_at')
            ->withTimestamps();
    }

    /**
     * Friendship rows this user initiated.
     *
     * @return HasMany<Friendship, $this>
     */
    public function friendships(): HasMany
    {
        return $this->hasMany(Friendship::class);
    }

    /**
     * Pending friend requests this user has received.
     *
     * @return HasMany<Friendship, $this>
     */
    public function incomingFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'friend_id')->where('status', 'pending');
    }

    /**
     * Pending friend requests this user has sent.
     *
     * @return HasMany<Friendship, $this>
     */
    public function outgoingFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'user_id')->where('status', 'pending');
    }

    /**
     * The ids of every accepted friend, from requests sent or received.
     *
     * @return Collection<int, int>
     */
    public function friendIds(): Collection
    {
        $ids = once(fn (): array => Friendship::query()
            ->where('status', 'accepted')
            ->where(function ($query): void {
                $query->where('user_id', $this->id)->orWhere('friend_id', $this->id);
            })
            ->get(['user_id', 'friend_id'])
            ->map(fn (Friendship $row): int => $row->user_id === $this->id ? $row->friend_id : $row->user_id)
            ->values()
            ->all());

        return collect($ids);
    }

    /**
     * Determine whether this user is an accepted friend of the given user.
     */
    public function isFriendsWith(User $other): bool
    {
        return $this->friendIds()->contains($other->id);
    }
}
