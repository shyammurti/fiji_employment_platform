@php
    $unread = auth()->user()->unreadNotifications()->latest()->take(8)->get();
    $unreadCount = auth()->user()->unreadNotifications()->count();
@endphp
<div class="relative" x-data="{ notifOpen: false }">
    <button type="button" @click="notifOpen = !notifOpen" aria-label="Notifications{{ $unreadCount ? ', '.$unreadCount.' unread' : '' }}" :aria-expanded="notifOpen" aria-controls="notifications-panel" class="relative rounded-xl p-2 text-slate-600 transition-colors hover:bg-slate-100 hover:text-fiji-900">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
        @if ($unreadCount > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-danger-600 px-1 text-[10px] font-bold tabular-nums text-white" aria-hidden="true">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
        @endif
    </button>
    <div id="notifications-panel" x-show="notifOpen" @click.outside="notifOpen = false" x-cloak class="fixed inset-x-4 top-20 z-40 max-h-[75vh] overflow-y-auto overscroll-contain rounded-2xl border border-slate-200 bg-surface py-2 shadow-pop sm:absolute sm:inset-x-auto sm:right-0 sm:top-auto sm:mt-3 sm:w-96">
        <div class="flex items-center justify-between px-4 py-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Notifications</p>
            @if ($unreadCount > 1)
                <form method="POST" action="{{ route('notifications.readAll') }}">@csrf @method('PATCH')<button type="submit" class="text-xs font-semibold text-ocean-700 hover:text-fiji-900">Mark all read</button></form>
            @endif
        </div>
        @forelse ($unread as $notification)
            <div class="border-t border-slate-100 px-4 py-3 text-sm">
                @if (! empty($notification->data['action_url']))
                    <a href="{{ $notification->data['action_url'] }}" class="block break-words text-slate-700 hover:text-ocean-700">{{ $notification->data['message'] ?? 'Update' }}</a>
                @else
                    <p class="break-words text-slate-700">{{ $notification->data['message'] ?? 'Update' }}</p>
                @endif
                <div class="mt-1 flex items-center justify-between">
                    <time class="text-xs text-slate-500" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                    <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="text-xs font-medium text-ocean-700 hover:text-fiji-900">Mark read</button>
                    </form>
                </div>
            </div>
        @empty
            <p class="border-t border-slate-100 px-4 py-6 text-center text-sm text-slate-600">You're all caught up.</p>
        @endforelse
    </div>
</div>
