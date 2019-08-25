<?php

namespace Sanchescom\Rest;

use Illuminate\Support\ServiceProvider;
use Sanchescom\Rest\Clients\ClientFactory;

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
