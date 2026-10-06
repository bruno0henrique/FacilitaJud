<?php

namespace App\Http\Controllers;

use App\Services\NeonAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class AuthController extends Controller
{
    public function login(): View
    {
        return view('login');
    }

    public function exchange(Request $request, NeonAuth $auth): JsonResponse
    {
        abort_unless(config('facilitajud.auth_provider') === 'neon', 503);
        try {
            $claims = $auth->verify($request->bearerToken() ?? '');
        } catch (Throwable) {
            return response()->json(['message' => 'Não foi possível validar sua sessão. Entre novamente.'], 401);
        }
        DB::transaction(function () use ($claims): void {
            if (! DB::table('members')->where('provider_id', $claims->sub)->exists()) {
                $name = mb_substr($claims->name ?? 'Meu escritório', 0, 200);
                $officeId = DB::table('offices')->insertGetId([
                    'name' => 'Escritório de '.$name, 'display_name' => $name,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('members')->insert([
                    'office_id' => $officeId, 'provider_id' => $claims->sub,
                    'name' => $name, 'email' => $claims->email ?? '', 'role' => 'Administrador(a)',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        $request->session()->regenerate();
        $request->session()->put('identity', ['id' => $claims->sub, 'expires_at' => $claims->exp]);

        return response()->json(['redirect' => route('workspace', ['module' => 'painel'])]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
