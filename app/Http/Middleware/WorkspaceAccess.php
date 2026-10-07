<?php

namespace App\Http\Middleware;

use App\Services\WorkspacePermissions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class WorkspaceAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $demo = ! $request->session()->has('identity') && config('facilitajud.demo') && app()->environment('local', 'testing')
            && (in_array($request->ip(), ['127.0.0.1', '::1'], true)
                || (config('facilitajud.demo_docker_loopback') && in_array($request->getHost(), ['localhost', '127.0.0.1'], true)));
        if ($demo) {
            $office = DB::table('offices')->where('is_demo', true)->first();
            abort_unless($office, 503, 'Execute o seeder demonstrativo antes de abrir o sistema.');
            $request->attributes->set('office_id', $office->id);
            $request->attributes->set('actor', $office->display_name);
            $request->attributes->set('demo', true);
            $member = DB::table('members')->where('office_id', $office->id)->first();
            $request->attributes->set('member_id', $member?->id);
            $request->attributes->set('is_admin', true);

            return $next($request);
        }
        if (! $request->session()->has('identity') || $request->session()->get('identity.expires_at', 0) <= time()) {
            $request->session()->forget('identity');

            return $request->expectsJson()
                ? response()->json(['message' => 'Sua sessão expirou. Entre novamente.'], 401)
                : redirect()->route('login');
        }
        $member = DB::table('members')->where('provider_id', $request->session()->get('identity.id'))->first();
        abort_unless($member, 403);
        $request->attributes->set('office_id', $member->office_id);
        $request->attributes->set('actor', $member->name);
        $request->attributes->set('demo', false);
        $request->attributes->set('member_id', $member->id);
        $request->attributes->set('is_admin', $member->account_type === 'admin');

        $category = $member->category_id ? DB::table('team_categories')->where('office_id', $member->office_id)->find($member->category_id) : null;
        $request->attributes->set('permissions', json_decode($member->permissions ?? $category?->permissions ?? 'null', true) ?? WorkspacePermissions::DEFAULTS);

        return $next($request);
    }
}
