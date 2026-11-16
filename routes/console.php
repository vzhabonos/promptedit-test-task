<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('pixelforge:reconcile')->everyMinute()->withoutOverlapping();

Artisan::command('app:make-admin {email}', function (string $email): void {
    $user = User::query()->where('email', $email)->firstOrFail();
    $user->forceFill(['is_admin' => true])->save();

    $this->components->info("{$user->email} can now see the admin report.");
})->purpose('Let a user see the admin spend report');
