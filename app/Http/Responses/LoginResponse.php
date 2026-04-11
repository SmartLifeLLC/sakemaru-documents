<?php

namespace App\Http\Responses;

use Filament\Facades\Filament;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $panelId = Filament::getCurrentPanel()->getId();

        return match ($panelId) {
            'admin' => redirect()->to('/admin'),
            'partner' => redirect()->to('/partner'),
            default => redirect()->to('/' . $panelId),
        };
    }
}
