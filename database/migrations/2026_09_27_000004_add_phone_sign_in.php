<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('login_phone', 20)->nullable()->unique();
        });
        $groups = DB::table('users')->whereNotNull('phone')->get(['id', 'phone'])->groupBy(fn ($user) => \App\Support\PhoneNumber::normalize($user->phone) ?? '');
        foreach ($groups as $phone => $users) {
            if ($phone !== '' && $users->count() === 1) {
                DB::table('users')->where('id', $users->first()->id)->update(['login_phone' => $phone]);
            }
        }
    }
    public function down(): void
    {
        // Keep nullable email so rollback cannot invalidate phone-only accounts.
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('login_phone'));
    }
};
