<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('users')
                ->select(['id', 'first_name', 'last_name', 'email', 'created_at'])
                ->orderBy('id')->chunkById(100, function ($users): void {
                    foreach ($users as $user) {
                        $exists = DB::table('audit_logs')
                            ->where('module', 'Users')
                            ->where('action', 'CREATE')
                            ->where('auditable_type', User::class)
                            ->where('auditable_id', $user->id)
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        $name = trim("{$user->first_name} {$user->last_name}") ?: $user->email;

                        DB::table('audit_logs')->insert([
                            // The account owner is not evidence of who created it.
                            'user_id' => null,
                            'user_role' => null,
                            'action' => 'CREATE',
                            'module' => 'Users',
                            'description' => "Reconstructed creation entry for account {$name} ({$user->email}); original creator unavailable.",
                            'auditable_type' => User::class,
                            'auditable_id' => $user->id,
                            'ip_address' => null,
                            'user_agent' => 'System Migration',
                            'created_at' => $user->created_at ?? now(),
                        ]);
                    }
                });
        });
    }

    public function down(): void
    {
        // Keep the append-only audit trail when rolling back a deployment.
    }
};
