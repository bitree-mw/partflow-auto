<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\LoginRequest;
use App\Services\AuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AuthSessionController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('web.dashboard');
        }

        return view('auth.login', [
            'title' => 'Login',
            'description' => 'Sign in to manage POS, purchases, stock, reports, and admin settings.',
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $authData = $this->authService->login(
            data: [
                ...$request->validated(),
                'device_name' => 'partflow-web-session',
            ],
            ipAddress: $request->ip()
        );

        $request->session()->regenerate();
        Auth::login($authData['user'], $request->boolean('remember'));

        $plainToken = $authData['token'];
        $request->session()->put('partflow_api_token', $plainToken);
        $request->session()->put('partflow_api_token_id', strtok($plainToken, '|') ?: null);

        return redirect()->intended(route('web.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($request->user()) {
            $this->authService->recordWebLogout($request->user());
        }

        $this->deleteSessionApiToken($request);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function deleteSessionApiToken(Request $request): void
    {
        $tokenId = $request->session()->get('partflow_api_token_id');
        $user = $request->user();

        if (! $tokenId || ! $user) {
            return;
        }

        PersonalAccessToken::query()
            ->whereKey($tokenId)
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->delete();
    }
}
