<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'referrer_id',
        'agent_code',
        'agent_tier',
        'status',
        'avatar',
        'password',
    ];

    const ROLE_HOME = [
        'super_admin' => 'hq.dashboard',
        'hq'          => 'hq.dashboard',
        'admin'       => 'admin.dashboard',
        'agent'       => 'agent.dashboard',
        'customer'    => 'customer.dashboard',
        'provider'    => 'provider.dashboard',
    ];

    public function provider()
    {
        return $this->hasOne(Provider::class);
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referrer_id');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class, 'earner_id')->latest();
    }

    public function withdrawals()
    {
        return $this->hasMany(Withdrawal::class)->latest();
    }

    public function agentBookings()
    {
        return $this->hasMany(Booking::class, 'agent_id');
    }

    public function streak()
    {
        return $this->hasOne(Streak::class);
    }

    public function checkins()
    {
        return $this->hasMany(Checkin::class);
    }

    public function pointTransactions()
    {
        return $this->hasMany(PointTransaction::class)->latest();
    }

    public function achievements()
    {
        return $this->belongsToMany(Achievement::class, 'agent_achievements')
            ->withPivot('unlocked_at')->withTimestamps();
    }

    public function redemptions()
    {
        return $this->hasMany(Redemption::class)->latest();
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class)->latest();
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class)->latest();
    }

    public function customerProfile()
    {
        return $this->hasOne(Customer::class);
    }

    public function unreadNotificationsCount(): int
    {
        return $this->notifications()->whereNull('read_at')->count();
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['super_admin', 'hq', 'admin']);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function homeRoute(): string
    {
        return self::ROLE_HOME[$this->role] ?? 'login';
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $a = isset($parts[0][0]) ? $parts[0][0] : '';
        $b = isset($parts[1][0]) ? $parts[1][0] : '';
        return strtoupper($a . $b) ?: 'U';
    }

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
        ];
    }
}
