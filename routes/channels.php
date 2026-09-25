<?php

use App\Models\User;
use App\Services\ChatService;
use App\Services\RaceService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('race.{key}', function (User $user, string $key) {
    return app(RaceService::class)->channelAuth($user, $key);
});

Broadcast::channel('chat.{low}.{high}', function (User $user, int $low, int $high) {
    return app(ChatService::class)->canJoinPairChannel($user, $low, $high);
});
