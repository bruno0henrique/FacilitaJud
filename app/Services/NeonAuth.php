<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use stdClass;

class NeonAuth
{
    public function verify(string $token): stdClass
    {
        $url = config('facilitajud.neon_url');
        if (! $url || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('Neon Auth não configurado.');
        }
        $origin = 'https://'.parse_url($url, PHP_URL_HOST);
        $jwksUrl = config('facilitajud.neon_jwks') ?: rtrim($url, '/').'/.well-known/jwks.json';
        $jwks = Cache::remember('neon-jwks-'.hash('sha256', $jwksUrl), 300, function () use ($jwksUrl): array {
            return Http::timeout(10)->get($jwksUrl)->throw()->json();
        });
        $keys = JWK::parseKeySet($jwks, 'EdDSA');
        $keys = array_filter($keys, fn ($key): bool => $key->getAlgorithm() === 'EdDSA');
        $claims = JWT::decode($token, $keys);
        $issuer = config('facilitajud.neon_issuer') ?: $origin;
        $audience = config('facilitajud.neon_audience') ?: $origin;
        if (($claims->iss ?? null) !== $issuer || ! in_array($audience, (array) ($claims->aud ?? []), true)
            || empty($claims->sub) || empty($claims->exp) || ($claims->exp <= time()) || ($claims->banned ?? false)) {
            throw new RuntimeException('Sessão inválida.');
        }

        return $claims;
    }
}
