<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Laravel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Mattdi\Fayda\Core\Exceptions\FaydaException;
use Mattdi\Fayda\FaydaClient;
use Mattdi\Fayda\Laravel\Auth\FaydaUserResolver;
use Mattdi\Fayda\Laravel\Events\FaydaLoginFailed;
use Mattdi\Fayda\Laravel\Events\FaydaLoginSucceeded;

final class FaydaController extends Controller
{
    public function redirect(Request $request, FaydaClient $client): RedirectResponse
    {
        if ((bool) config('fayda.auth.login', false)) {
            $previous = url()->previous();
            $current = $request->fullUrl();

            if (is_string($previous) && $previous !== '' && $previous !== $current) {
                $request->session()->put('fayda.intended', $previous);
            }
        }

        return redirect()->away($client->authorizationUrl());
    }

    public function callback(
        Request $request,
        FaydaClient $client,
        FaydaUserResolver $resolver,
    ): RedirectResponse {
        try {
            $result = $client->authenticate(
                code: (string) $request->input('code', ''),
                state: $this->stringInput($request, 'state'),
                error: $this->stringInput($request, 'error'),
                errorDescription: $this->stringInput($request, 'error_description'),
            );

            $authenticatable = $resolver->resolve($result->user);

            if ($authenticatable !== null && (bool) config('fayda.auth.login', false)) {
                Auth::guard((string) config('fayda.auth.guard', 'web'))->login($authenticatable);
                $request->session()->regenerate();
            }

            event(new FaydaLoginSucceeded($result, $authenticatable));

            return redirect()->to($this->intendedUrl($request));
        } catch (FaydaException $e) {
            event(new FaydaLoginFailed($e));
            report($e);

            return redirect()
                ->to((string) config('fayda.auth.redirect_after_login', '/'))
                ->withErrors(['fayda' => $e->getMessage()]);
        }
    }

    private function intendedUrl(Request $request): string
    {
        $intended = $request->session()->pull('fayda.intended');
        $fallback = (string) config('fayda.auth.redirect_after_login', '/');

        if (! is_string($intended) || $intended === '') {
            return $fallback;
        }

        $base = rtrim(url('/'), '/');

        return str_starts_with($intended, $base.'/') || $intended === $base ? $intended : $fallback;
    }

    private function stringInput(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
