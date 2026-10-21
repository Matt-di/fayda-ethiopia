<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Laravel;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Mattdi\Fayda\Core\Contracts\StateStore;
use Mattdi\Fayda\FaydaClient;
use Mattdi\Fayda\Laravel\Auth\EloquentUserResolver;
use Mattdi\Fayda\Laravel\Auth\FaydaUserResolver;
use Mattdi\Fayda\Laravel\Auth\NullUserResolver;

final class FaydaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/fayda.php', 'fayda');

        $this->app->scoped(StateStore::class, static function (Application $app): StateStore {
            return new SessionStateStore($app['session.store']);
        });

        $this->app->scoped(FaydaClient::class, static function (Application $app): FaydaClient {
            $config = (array) $app['config']->get('fayda', []);
            $httpConfig = is_array($config['http'] ?? null) ? $config['http'] : [];
            $factory = new HttpFactory;

            $http = new GuzzleClient([
                'timeout' => (float) ($httpConfig['timeout'] ?? 15),
                'connect_timeout' => (float) ($httpConfig['connect_timeout'] ?? 5),
            ]);

            $cache = null;

            if ($app->bound('cache')) {
                $store = $config['cache_store'] ?? null;
                $cache = is_string($store) && $store !== ''
                    ? $app['cache']->store($store)
                    : $app['cache']->store();
            }

            return new FaydaClient(
                config: $config,
                http: $http,
                cache: $cache,
                states: $app->make(StateStore::class),
                requestFactory: $factory,
                streamFactory: $factory,
            );
        });

        $this->app->alias(FaydaClient::class, 'fayda');

        $this->app->singleton(FaydaUserResolver::class, static function (Application $app): FaydaUserResolver {
            $resolver = $app['config']->get('fayda.user_resolver');

            if (is_string($resolver) && $resolver !== '') {
                $instance = $app->make($resolver);

                if ($instance instanceof FaydaUserResolver) {
                    return $instance;
                }
            }

            if ((bool) $app['config']->get('fayda.persistence.enabled', false)) {
                return $app->make(EloquentUserResolver::class);
            }

            return new NullUserResolver;
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/fayda.php' => config_path('fayda.php'),
        ], 'fayda-config');

        $this->publishes([
            __DIR__.'/../../database/migrations/2026_01_01_000000_create_fayda_identities_table.php' => database_path('migrations/2026_01_01_000000_create_fayda_identities_table.php'),
        ], 'fayda-migrations');

        if ((bool) $this->app['config']->get('fayda.persistence.enabled', false)) {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }

        if ((bool) $this->app['config']->get('fayda.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../../routes/fayda.php');
        }
    }
}
