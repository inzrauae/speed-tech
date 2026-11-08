<?php

namespace App\Http\Controllers;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\CatalogStructureSeeder;
use Database\Seeders\DemoProductSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * One-time browser installer for hosts without terminal access (e.g. cPanel).
 * Disabled unless INSTALL_TOKEN is set in .env; remove the token afterwards.
 *
 *   /install/{token}          create tables, admin user, brands / part types
 *   /install/{token}?demo=1   also load the sample products
 */
class InstallController extends Controller
{
    public function __invoke(Request $request, string $token): Response
    {
        $expected = (string) config('speedtech.install_token');
        abort_if(strlen($expected) < 20 || ! hash_equals($expected, $token), 404);

        $log = [];
        AdminUserSeeder::$generatedPassword = null;
        try {
            Artisan::call('migrate', ['--force' => true]);
            $log[] = trim(Artisan::output()) ?: 'Database tables are up to date.';

            $freshInstall = ! User::where('is_admin', true)->exists();
            Artisan::call('db:seed', ['--class' => AdminUserSeeder::class, '--force' => true]);
            if ($freshInstall) {
                Artisan::call('db:seed', ['--class' => CatalogStructureSeeder::class, '--force' => true]);
                $log[] = 'Added laptop brands, models, part types and shop categories.';
            }
            if ($request->boolean('demo')) {
                Artisan::call('db:seed', ['--class' => DemoProductSeeder::class, '--force' => true]);
                $log[] = 'Added SAMPLE products (replace the prices and stock in /admin).';
            }

            $log[] = '';
            $log[] = 'Admin sign-in: '.url('/admin');
            $log[] = 'E-mail:   '.config('speedtech.admin_email');
            $log[] = AdminUserSeeder::$generatedPassword
                ? 'Password: '.AdminUserSeeder::$generatedPassword.'   <-- copy this now, it is shown only once'
                : 'Password: the ADMIN_PASSWORD from your .env (or unchanged if the admin already existed)';
            $log[] = '';
            $log[] = 'IMPORTANT: now delete the INSTALL_TOKEN line from .env so this page is switched off.';
        } catch (Throwable $e) {
            $log[] = 'Install failed: '.$e->getMessage();
            $log[] = 'Check the DB_DATABASE, DB_USERNAME and DB_PASSWORD values in .env.';
        }

        return response(implode("\n", $log), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'no-store',
        ]);
    }
}
