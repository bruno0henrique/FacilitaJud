<?php

namespace App\Services;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class NeonSession
{
    public function call(Request $request, string $endpoint, array $data = [], bool $get = false): Response
    {
        $base = rtrim((string) config('facilitajud.neon_url'), '/');
        abort_unless(str_starts_with($base, 'https://'), 503, 'Autenticação ainda não configurada.');
        $stored = $request->session()->get('neon_cookies');
        $cookies = $stored ? json_decode(Crypt::decryptString($stored), true) : [];
        $jar = new CookieJar(false, $cookies);
        $client = Http::acceptJson()->timeout(20)
            ->withHeaders(['Origin' => rtrim(config('app.url'), '/'), 'x-neon-auth-middleware' => 'true'])->withOptions(['cookies' => $jar]);
        $response = $get ? $client->get($base.'/'.$endpoint) : $client->post($base.'/'.$endpoint, $data);
        $jar->extractCookies(new \GuzzleHttp\Psr7\Request($get ? 'GET' : 'POST', $base.'/'.$endpoint), $response->toPsrResponse());
        $request->session()->put('neon_cookies', Crypt::encryptString(json_encode($jar->toArray(), JSON_THROW_ON_ERROR)));

        return $response;
    }
}
