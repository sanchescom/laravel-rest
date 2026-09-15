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

        Rest::memoize((bool) $this->app['config']->get('rest.memoize', false));

        if ($this->app->bound('events')) {
            $events = $this->app->make('events');

            Model::setEventDispatcher($events);

            // Class-name strings: neither Octane nor the queue component is a dependency.
            foreach (['Laravel\Octane\Events\RequestReceived', 'Illuminate\Queue\Events\JobProcessing'] as $event) {
                $events->listen($event, static fn () => Rest::flushMemo());
            }
        }

        $cache = $this->app['config']->get('rest.cache');

        if (is_array($cache) && $this->app->bound('cache')) {
            Model::setCacheStore(
                $this->app->make('cache')->store($cache['store'] ?? null),
                (int) ($cache['ttl'] ?? 300),
            );
        }
    }
}
