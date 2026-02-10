<?php

namespace App\Models;

use App\Models\Base\SakemaruAuthenticatable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class Partner extends SakemaruAuthenticatable implements FilamentUser
{
    protected $table = 'partners';

    protected $fillable = [
        'code',
        'name',
        'email',
        'password',
        'is_supplier',
        'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_supplier' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }
}
