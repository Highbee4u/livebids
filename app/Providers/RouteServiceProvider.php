<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     */
    public const HOME = '/';

    /**
     * Bootstrap route services.
     */
    public function boot(): void
    {
        $this->mapApiRoutes();
    }

    /**
     * Register domain-specific API routes at the provider level.
     *
     * Routes are assigned the "api" middleware group and prefixed with "api/v1".
     */
    protected function mapApiRoutes(): void
    {
        $v1RouteFiles = [
            'auth' => base_path('routes/api/v1/auth.php'),
            'discovery' => base_path('routes/api/v1/discovery.php'),
            'kyc' => base_path('routes/api/v1/kyc.php'),
            'auctions' => base_path('routes/api/v1/auctions.php'),
            'orders' => base_path('routes/api/v1/orders.php'),
            'wallet' => base_path('routes/api/v1/wallet.php'),
            'webhooks' => base_path('routes/api/v1/webhooks.php'),
            'admin' => base_path('routes/api/v1/admin.php'),
        ];

        foreach ($v1RouteFiles as $file) {
            if (file_exists($file)) {
                Route::middleware('api')
                    ->prefix('api/v1')
                    ->group($file);
            }
        }
    }
}
