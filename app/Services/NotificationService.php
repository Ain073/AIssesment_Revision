<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use Illuminate\Support\Collection;

class NotificationService
{
    public function send(User $user, string $title, string $message, ?string $link = null, string $type = 'info'): void
    {
        AppNotification::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
        ]);
    }

    public function sendToMany(Collection $users, string $title, string $message, ?string $link = null, string $type = 'info'): void
    {
        $users
            ->filter()
            ->unique('id')
            ->each(fn (User $user) => $this->send($user, $title, $message, $link, $type));
    }
}
