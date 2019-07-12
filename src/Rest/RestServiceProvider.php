<?php

namespace App\Rest;

use App\Rest\Clients\ClientFactory;
use Illuminate\Support\ServiceProvider;

class RestServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        Model::setClientResolver($this->app['rest']);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerClientServices();
    }

    /**
     * Register the primary database bindings.
     *
     * @return void
     */
    protected function registerClientServices()
    {
        $this->app->singleton('rest.factory', function ($app) {
            return new ClientFactory($app);
        });

        $this->app->singleton('rest', function ($app) {
            return new ClientManager($app, $app['rest.factory']);
        });
    }
}
