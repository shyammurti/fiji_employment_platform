<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PlatformAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Module M5 Admin Governance & Verification, developed by Maimuna Nisha (S11229653).
 */
class UserAccessController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();
        if ($search = trim((string) $request->input('q'))) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%'));
        }
        return view('admin.users.index', ['users' => $query->orderBy('name')->paginate(\App\Support\PerPage::resolve())->withQueryString()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', 'in:jobseeker,employer,mentor,admin'], 'reason' => ['required', 'string', 'max:1000'], 'password' => ['required', 'current_password']]);
        DB::transaction(function () use ($request, $user, $data) {
            $admins = User::where('role', 'admin')->orderBy('id')->lockForUpdate()->get();
            $target = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($target->role === $data['role']) {
                throw ValidationException::withMessages(['role' => 'Choose a different role.']);
            }
            if ($target->isAdmin() && $admins->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'At least one administrator must remain.']);
            }
            if ($target->jobListings()->exists() || $target->jobApplications()->exists() || $target->mentorSessions()->exists() || $target->menteeSessions()->exists()) {
                throw ValidationException::withMessages(['role' => 'This account has role-specific records. Its role must be retained so participants can still access their history.']);
            }
            DB::table('role_changes')->insert(['actor_id' => $request->user()->id, 'user_id' => $target->id, 'previous_role' => $target->role, 'new_role' => $data['role'], 'reason' => $data['reason'], 'created_at' => now(), 'updated_at' => now()]);
            $target->update(['role' => $data['role'], 'employer_approval_status' => $data['role'] === 'employer' ? 'pending' : 'approved']);
        });
        $user->refresh()->notify(new PlatformAlert('Your account role is now '.$user->role.'. '.$data['reason'], 'Open dashboard', route('dashboard')));
        return redirect()->route($request->user()->fresh()->isAdmin() ? 'admin.users.index' : 'dashboard')->with('status', 'Account role updated. Employer accounts require separate approval.');
    }
}
