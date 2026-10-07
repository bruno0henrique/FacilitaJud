<?php

namespace App\Http\Controllers;

use App\Services\NeonAuth;
use App\Services\NeonSession;
use App\Services\PresentationData;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
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

        return $this->establish($request, $claims);
    }

    private function establish(Request $request, object $claims): JsonResponse
    {
        DB::transaction(function () use ($claims, $request): void {
            if ($request->filled('invitation')) {
                $invite = DB::table('team_invitations')->where('token_hash', hash('sha256', $request->string('invitation')->toString()))->lockForUpdate()->first();
                abort_unless($invite && ! $invite->accepted_at && Carbon::parse($invite->expires_at)->isFuture()
                    && strcasecmp($invite->email, $claims->email ?? '') === 0, 403, 'Convite inválido, expirado ou destinado a outro e-mail.');
                $existing = DB::table('members')->where('provider_id', $claims->sub)->first();
                abort_if($existing && $existing->office_id !== $invite->office_id, 409, 'Esta conta já pertence a outro escritório. Use o e-mail convidado para uma nova conta.');
                if (! $existing) {
                    DB::table('members')->insert(['office_id' => $invite->office_id, 'provider_id' => $claims->sub, 'name' => $invite->name, 'email' => $invite->email, 'role' => 'Associado(a)', 'account_type' => 'associate', 'category_id' => $invite->category_id, 'permissions' => $invite->permissions, 'responsibilities' => $invite->responsibilities, 'created_at' => now(), 'updated_at' => now()]);
                }
                DB::table('team_invitations')->where('id', $invite->id)->update(['accepted_at' => now()]);
            }
            if (! DB::table('members')->where('provider_id', $claims->sub)->exists()) {
                $name = mb_substr($claims->name ?? 'Meu escritório', 0, 200);
                $officeId = DB::table('offices')->insertGetId([
                    'name' => 'Escritório de '.$name, 'display_name' => $name,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('members')->insert([
                    'office_id' => $officeId, 'provider_id' => $claims->sub,
                    'name' => $name, 'email' => $claims->email ?? '', 'role' => 'Administrador(a)', 'account_type' => 'admin',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        $request->session()->regenerate();
        $request->session()->put('identity', ['id' => $claims->sub, 'expires_at' => $claims->exp]);
        $presentationEmail = config('facilitajud.presentation_email');
        if ($presentationEmail && strcasecmp($presentationEmail, $claims->email ?? '') === 0) {
            $member = DB::table('members')->where('provider_id', $claims->sub)->where('account_type', 'admin')->first();
            if ($member) {
                try {
                    app(PresentationData::class)->populate((int) $member->office_id);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }
        }

        return response()->json(['redirect' => route('workspace', ['module' => 'painel'])]);
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->session()->has('neon_cookies')) {
            try {
                app(NeonSession::class)->call($request, 'sign-out');
            } catch (Throwable) {
            }
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function action(Request $request, string $action, NeonSession $neon, NeonAuth $auth): JsonResponse
    {
        abort_unless(config('facilitajud.auth_provider') === 'neon', 503);
        $endpoints = ['login' => 'sign-in/email', 'register' => 'sign-up/email', 'recover' => 'request-password-reset', 'reset' => 'reset-password'];
        abort_unless(isset($endpoints[$action]) || $action === 'refresh', 404);
        if ($action === 'refresh' && (! $request->session()->has('identity') || ! $request->session()->has('neon_cookies'))) {
            return response()->json(['message' => 'Sua sessão expirou. Entre novamente.'], 401);
        }
        if ($action !== 'refresh') {
            $rules = match ($action) {
                'reset' => ['token' => 'required|string|max:1000', 'newPassword' => 'required|string|min:8|max:128'],
                'recover' => ['email' => 'required|email|max:254'],
                default => ['email' => 'required|email|max:254', 'password' => 'required|string|min:8|max:128'] + ($action === 'register' ? ['name' => 'required|string|max:120'] : []),
            };
            $data = $request->validate($rules);
            if ($action === 'recover') {
                $data['redirectTo'] = rtrim(config('app.url'), '/').'/entrar';
            }
            try {
                $response = $neon->call($request, $endpoints[$action], $data);
            } catch (ConnectionException) {
                return response()->json(['message' => 'O serviço de autenticação está indisponível. Tente novamente em instantes.'], 503);
            }
            if (! $response->successful()) {
                $code = $response->json('code');
                $message = match ($code) {
                    'USER_ALREADY_EXISTS', 'USER_ALREADY_EXISTS_USE_ANOTHER_EMAIL' => 'Este e-mail já tem conta. Clique em Já tenho conta e entre com sua senha.',
                    'EMAIL_NOT_VERIFIED' => 'Confirme seu e-mail antes de entrar.',
                    default => $action === 'register' ? 'Não foi possível criar a conta. Confira os dados ou entre se já se cadastrou.' : 'Confira seu e-mail e senha ou recupere o acesso.',
                };

                return response()->json(['message' => $message], 422);
            }
            if (in_array($action, ['recover', 'reset'], true)) {
                return response()->json(['ok' => true]);
            }
        }
        try {
            $token = $neon->call($request, 'token', [], true)->json('token');
            $claims = $auth->verify(is_string($token) ? $token : '');

        } catch (Throwable) {
            if ($action === 'register') {
                return response()->json(['created' => true, 'message' => 'Conta criada. Confirme seu e-mail, se solicitado, e entre com sua senha.']);
            }

            return response()->json(['message' => 'Não foi possível confirmar o acesso. Entre novamente.'], 401);
        }

        return $this->establish($request, $claims);
    }
}
