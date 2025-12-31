<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogSuccessfulLogin
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if ($event->user instanceof \App\Models\User) {
            $service = app(\App\Services\AuditLogService::class);
            $service->log($event->user, 'Login', 'User logged in.');
        }
    }
}
