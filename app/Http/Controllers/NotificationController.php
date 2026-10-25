<?php

namespace App\Http\Controllers;

use App\Support\FollowUpReminders;
use App\Support\NotificationPresenter;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        FollowUpReminders::dispatchDue();
        $user = $request->user();
        $notifications = $user->notifications()->latest()->limit(50)->get()
            ->map(fn ($notification) => ['model' => $notification] + NotificationPresenter::present($notification, $user));

        return view('notifications.index', compact('notifications'));
    }

    // Polled every 30 seconds by open pages; returns the unread count and the newest unread items for toasts.
    public function poll(Request $request)
    {
        FollowUpReminders::dispatchDue();
        $user = $request->user();
        $latest = $user->unreadNotifications()->latest()->limit(5)->get()->map(function ($notification) use ($user) {
            $view = NotificationPresenter::present($notification, $user);

            return ['id' => $notification->id, 'headline' => $view['headline'], 'urgent' => $view['urgent'], 'url' => $view['url'] ? route('notifications.open', $notification->id) : null];
        });

        return response()->json(['unread' => $user->unreadNotifications()->count(), 'latest' => $latest])->header('Cache-Control', 'no-store, private');
    }

    // Latest notifications for the header bell's dropdown, read and unread.
    public function panel(Request $request)
    {
        $user = $request->user();
        $items = $user->notifications()->latest()->limit(8)->get()->map(function ($notification) use ($user) {
            $view = NotificationPresenter::present($notification, $user);

            return [
                'id' => $notification->id, 'headline' => $view['headline'], 'details' => $view['details'], 'urgent' => $view['urgent'],
                'unread' => $notification->read_at === null, 'time' => $notification->created_at->timezone('Asia/Manila')->format('M j, g:i A'),
                'url' => $view['url'] ? route('notifications.open', $notification->id) : null,
            ];
        });

        return response()->json(['items' => $items])->header('Cache-Control', 'no-store, private');
    }

    public function open(Request $request, string $notification)
    {
        $notification = $request->user()->notifications()->findOrFail($notification);
        $notification->markAsRead();
        $url = NotificationPresenter::present($notification, $request->user())['url'];
        abort_unless($url, 403);

        return redirect($url);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked as read.');
    }
}
