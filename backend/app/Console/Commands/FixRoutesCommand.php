<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class FixRoutesCommand extends Command
{
    protected $signature = 'mt5:routes-fix';

    protected $description = 'Delete stale route cache and verify dashboard routes';

    public function handle(): int
    {
        $cacheDir = base_path('bootstrap/cache');
        $routeCache = $cacheDir.'/routes-v7.php';

        if (File::exists($routeCache)) {
            File::delete($routeCache);
            $this->warn('Deleted stale route cache: bootstrap/cache/routes-v7.php');
        } else {
            $this->line('No route cache file found.');
        }

        $this->call('route:clear');
        $this->call('optimize:clear');

        $this->newLine();
        $this->line('Required files:');

        $files = [
            'routes/web.php',
            'routes/dashboard.php',
            'app/Http/Controllers/Dashboard/DashboardController.php',
            'app/Http/Controllers/Dashboard/DashboardAuthController.php',
            'app/Http/Middleware/VerifyDashboardAuth.php',
        ];

        $allExist = true;
        foreach ($files as $file) {
            $path = base_path($file);
            $exists = File::exists($path);
            $this->line(sprintf('  [%s] %s', $exists ? 'OK' : 'MISSING', $file));
            $allExist = $allExist && $exists;
        }

        if (! $allExist) {
            $this->newLine();
            $this->error('Dashboard files missing — run: git pull origin main');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Cache cleared. Run this in a fresh command to verify routes:');
        $this->line('  php artisan route:list | grep dashboard');
        $this->newLine();
        $this->line('Then restart PHP-FPM:');
        $this->line('  sudo systemctl restart php8.5-fpm');

        return self::SUCCESS;
    }
}
