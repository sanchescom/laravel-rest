<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use Illuminate\Support\ServiceProvider;
use Sanchescom\Rest\Clients\ClientFactory;
use Sanchescom\Rest\Contracts\ClientResolverInterface;

class RestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/rest.php', 'rest');

        $this->app->singleton('rest', function ($app) {
            return new ClientManager($app['config'], new ClientFactory);
        });

        $this->app->alias('rest', ClientResolverInterface::class);
        $this->app->alias('rest', ClientManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/rest.php' => config_path('rest.php'),
        ], 'rest-config');

        Model::setClientResolver($this->app->make(ClientResolverInterface::class));

        if ($this->app->bound('events')) {
            Model::setEventDispatcher($this->app->make('events'));
        }
    }
}
