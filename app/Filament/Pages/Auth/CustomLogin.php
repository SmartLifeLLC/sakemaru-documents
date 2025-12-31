<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

class CustomLogin extends Login
{
    public function getView(): string
    {
        return 'filament.pages.auth.custom-login';
    }

    public function getLayout(): string
    {
        return 'filament.pages.auth.custom-layout';
    }

    public function authenticate(): ?\Filament\Auth\Http\Responses\Contracts\LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (\DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        if (! \Filament\Facades\Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = \Filament\Facades\Filament::auth()->user();

        if (
            ($user instanceof \Filament\Models\Contracts\FilamentUser) &&
            (! $user->canAccessPanel(\Filament\Facades\Filament::getCurrentPanel()))
        ) {
            \Filament\Facades\Filament::auth()->logout();

            $this->throwFailureValidationException();
        }

        session()->regenerate();

        // Dispatch event for video playback instead of immediate redirect
        $url = session()->pull('url.intended', \Filament\Facades\Filament::getUrl());
        $this->dispatch('login-success', url: $url);

        return null;
    }
}
