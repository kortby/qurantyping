<?php

use App\Models\User;
use App\Services\RaceService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('race.{key}', function (User $user, string $key) {
    return app(RaceService::class)->channelAuth($user, $key);
});
