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
        $this->app->singleton('rest', function ($app) {
            return new ClientManager($app, new ClientFactory());
        });
    }
}
