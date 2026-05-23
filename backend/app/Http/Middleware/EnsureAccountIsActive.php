<?php

namespace App\Http\Middleware;

use App\Models\Account;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user instanceof Account) {
            return $next($request);
        }

        $account = Account::query()->find($user->getKey());

        if ($account !== null && $account->is_active) {
            return $next($request);
        }

        try {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        } catch (Throwable $e) {
            Log::error('Failed to logout inactive account session', [
                'account_id' => $user->getKey(),
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Tài khoản đã bị khóa hoặc chưa được kích hoạt.',
        ], Response::HTTP_FORBIDDEN);
    }
}
