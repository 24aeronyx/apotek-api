<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        User::whereNull('username')->get()->each(function (User $user): void {
            $base = Str::of(Str::before($user->email, '@'))
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '-')
                ->trim('-')
                ->toString();
            $username = $base ?: 'user';

            if (User::where('username', $username)->whereKeyNot($user->id)->exists()) {
                $username .= '-' . $user->id;
            }

            $user->update(['username' => $username]);
        });
    }

    public function down(): void
    {
        // Existing usernames are retained on rollback to avoid losing account identifiers.
    }
};
