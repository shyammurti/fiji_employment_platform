<x-app-layout title="User access">
    <x-slot name="header"><h1 class="page-title">User access</h1><p class="page-intro">Manage platform roles. Changes are recorded and shared with the account holder.</p></x-slot>
    <form method="GET" class="card mb-6 flex flex-wrap items-end gap-3"><div class="min-w-0 flex-1"><label for="user-search" class="label">Find an account</label><input id="user-search" class="input" name="q" value="{{ request('q') }}" placeholder="Name or email"></div><button class="btn-primary">Search accounts</button></form>
    <div class="space-y-4">@foreach ($users as $account)
        <section class="card" x-data="{ expanded: false }">
            <div class="flex flex-wrap items-center justify-between gap-4"><div><h3 class="font-semibold">{{ $account->name }}</h3><p class="text-sm text-slate-500">{{ $account->email ?: $account->phone }}</p></div><div class="flex items-center gap-3"><span class="status-pill bg-ocean-100 text-ocean-700">{{ ucfirst($account->role) }}</span><button type="button" class="btn-secondary" @click="expanded = !expanded" :aria-expanded="expanded">Manage role</button></div></div>
            <form x-show="expanded" x-cloak method="POST" action="{{ route('admin.users.update', $account) }}" class="mt-5 grid gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2">@csrf @method('PATCH')
                <div><label for="role-{{ $account->id }}" class="label">New role</label><select id="role-{{ $account->id }}" name="role" class="input" required>@foreach (['jobseeker', 'employer', 'mentor', 'admin'] as $role)<option value="{{ $role }}" @selected($account->role === $role)>{{ ucfirst($role) }}</option>@endforeach</select></div>
                <div><label for="admin-password-{{ $account->id }}" class="label">Your administrator password</label><input id="admin-password-{{ $account->id }}" type="password" name="password" autocomplete="current-password" required class="input"></div>
                <div class="sm:col-span-2"><label for="reason-{{ $account->id }}" class="label">Reason sent to the user</label><textarea id="reason-{{ $account->id }}" name="reason" required maxlength="1000" class="input"></textarea></div>
                <p class="text-xs text-slate-500 sm:col-span-2">Accounts with applications, vacancies or mentorship records retain their role to preserve access. The last administrator cannot be removed.</p><button class="btn-primary sm:col-span-2">Confirm role change</button>
            </form>
        </section>
    @endforeach</div><div class="mt-6">{{ $users->links() }}</div>
</x-app-layout>
