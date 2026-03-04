<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Player extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'pseudo',
        'avatar_url',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [];

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function sessionPlayers(): HasMany
    {
        return $this->hasMany(SessionPlayer::class);
    }
}
