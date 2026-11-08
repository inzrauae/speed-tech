<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the first admin from ADMIN_EMAIL / ADMIN_PASSWORD (config/speedtech.php).
 * Without ADMIN_PASSWORD a random one is generated and shown once.
 */
class AdminUserSeeder extends Seeder
{
    /** The generated password, so the browser installer can show it once. */
    public static ?string $generatedPassword = null;

    public function run(): void
    {
        $email = config('speedtech.admin_email') ?: 'admin@speedtech.lk';
        if (User::where('email', $email)->exists()) {
            $this->command?->info("Admin {$email} already exists; password unchanged.");

            return;
        }

        $configured = config('speedtech.admin_password');
        $password = $configured ?: (self::$generatedPassword = Str::password(16, symbols: false));

        $user = new User(['name' => 'SpeedTech Admin', 'email' => $email, 'password' => $password]);
        $user->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();

        $this->command?->warn("Admin login: {$email}".($configured ? ' (password from ADMIN_PASSWORD)' : " / {$password}  <- save this now"));
    }
}
