<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Tests;

use Mattdi\Fayda\Laravel\Facades\Fayda;
use Mattdi\Fayda\Laravel\FaydaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [FaydaServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Fayda' => Fayda::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('session.driver', 'array');
        $app['config']->set('cache.default', 'array');

        $app['config']->set('fayda.issuer', 'https://idp.test');
        $app['config']->set('fayda.discovery.enabled', false);
        $app['config']->set('fayda.endpoints', [
            'authorization' => 'https://idp.test/authorize',
            'token' => 'https://idp.test/token',
            'userinfo' => 'https://idp.test/userinfo',
            'jwks' => 'https://idp.test/jwks',
        ]);
        $app['config']->set('fayda.client.id', 'client-1');
        $app['config']->set('fayda.client.private_key_path', __DIR__.'/Fixtures/private-key.pem');
        $app['config']->set('fayda.client.key_id', 'test-key');
        $app['config']->set('fayda.client.assertion_ttl', 300);
        $app['config']->set('fayda.redirect_uri', 'https://app.test/auth/fayda/callback');
        $app['config']->set('fayda.scopes', ['openid', 'profile', 'email']);
        $app['config']->set('fayda.acr_values', ['mosip:idp:acr:generated-code']);
        $app['config']->set('fayda.claims', []);
        $app['config']->set('fayda.clock_skew', 0);
        $app['config']->set('fayda.routes.enabled', true);
        $app['config']->set('fayda.routes.prefix', 'auth/fayda');
        $app['config']->set('fayda.auth.login', false);
    }
}
