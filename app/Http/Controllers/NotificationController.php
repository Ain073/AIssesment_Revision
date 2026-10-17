<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->snapshot($request));
    }

    public function read(Request $request, AppNotification $notification): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->status === 'active', 403);
        abort_unless($notification->user_id === $request->user()->id, 403);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        $url = $notification->link ?: url()->previous();

        if ($request->expectsJson()) {
            return response()->json($this->snapshot($request) + ['url' => $url]);
        }

        return redirect($url);
    }

    public function readAll(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->status === 'active', 403);
        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json($this->snapshot($request) + ['message' => 'Notifications marked as read.']);
        }

        return back();
    }

    private function snapshot(Request $request): array
    {
        $user = $request->user();
        abort_unless($user->status === 'active', 403);

        $unreadCount = $user->appNotifications()->whereNull('read_at')->count();

        return [
            'unread_count' => $unreadCount,
            'html' => view('partials.notification-menu', [
                'portalNotifications' => $user->appNotifications()->latest('notification_id')->limit(8)->get(),
                'portalUnreadNotifications' => $unreadCount,
            ])->render(),
        ];
    }
}
