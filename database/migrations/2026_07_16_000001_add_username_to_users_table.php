<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->unique()->after('name');
            }
        });

        $usedUsernames = [];

        DB::table('users')
            ->select(['id', 'email', 'username'])
            ->orderBy('id')
            ->get()
            ->each(function (object $user) use (&$usedUsernames) {
                if (filled($user->username)) {
                    $usedUsernames[] = Str::lower($user->username);

                    return;
                }

                $baseUsername = Str::slug(Str::before((string) $user->email, '@')) ?: 'user';
                $username = $baseUsername;
                $suffix = 2;

                while (in_array($username, $usedUsernames, true)) {
                    $username = "{$baseUsername}{$suffix}";
                    $suffix++;
                }

                $usedUsernames[] = $username;

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['username' => $username]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'username')) {
                $table->dropUnique(['username']);
                $table->dropColumn('username');
            }
        });
    }
};
