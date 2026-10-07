<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NeonSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_keeps_upstream_cookie_server_side_and_login_reuses_the_office(): void
    {
        config(['facilitajud.demo' => false, 'facilitajud.neon_url' => 'https://auth.example.test/auth', 'facilitajud.neon_jwks' => null]);
        $pair = sodium_crypto_sign_keypair();
        $jwt = JWT::encode(['sub' => 'new-user', 'name' => 'Ana', 'email' => 'ana@example.test', 'iss' => 'https://auth.example.test', 'aud' => 'https://auth.example.test', 'exp' => time() + 900], base64_encode(sodium_crypto_sign_secretkey($pair)), 'EdDSA', 'key');
        Http::preventStrayRequests();
        Http::fake([
            'https://auth.example.test/auth/sign-up/email' => Http::response(['user' => ['id' => 'new-user']], 200, ['Set-Cookie' => 'neon-auth.session_token=test-session; Path=/; Secure; HttpOnly']),
            'https://auth.example.test/auth/sign-in/email' => Http::response(['user' => ['id' => 'new-user']], 200, ['Set-Cookie' => 'neon-auth.session_token=test-session; Path=/; Secure; HttpOnly']),
            'https://auth.example.test/auth/token' => Http::response(['token' => $jwt]),
            'https://auth.example.test/auth/.well-known/jwks.json' => Http::response(['keys' => [['kty' => 'OKP', 'crv' => 'Ed25519', 'kid' => 'key', 'alg' => 'EdDSA', 'x' => JWT::urlsafeB64Encode(sodium_crypto_sign_publickey($pair))]]]),
        ]);
        $this->postJson('/auth/neon/register', ['name' => 'Ana', 'email' => 'ana@example.test', 'password' => 'test-password'])->assertOk()->assertJsonPath('redirect', url('/painel'))->assertDontSee($jwt)->assertSessionHas('neon_cookies');
        $this->get('/painel')->assertOk();
        Http::assertSent(fn ($request) => $request->url() === 'https://auth.example.test/auth/token' && str_contains($request->header('Cookie')[0] ?? '', 'test-session'));
        $this->assertStringNotContainsString('test-session', session('neon_cookies'));
        $this->postJson('/auth/neon/login', ['email' => 'ana@example.test', 'password' => 'test-password'])->assertOk();
        $this->assertSame(1, DB::table('members')->count());
        $this->postJson('/auth/neon/refresh')->assertOk();
    }

    public function test_signup_without_session_is_not_reported_as_failed_and_existing_email_is_clear(): void
    {
        config(['facilitajud.neon_url' => 'https://auth.example.test/auth']);
        Http::preventStrayRequests();
        Http::fake(['*/sign-up/email' => Http::sequence()->push(['user' => ['id' => 'created']])->push(['code' => 'USER_ALREADY_EXISTS'], 422), '*/token' => Http::response([], 401)]);
        $payload = ['name' => 'Ana', 'email' => 'ana@example.test', 'password' => 'test-password'];
        $this->postJson('/auth/neon/register', $payload)->assertOk()->assertJsonPath('created', true);
        $this->postJson('/auth/neon/register', $payload)->assertUnprocessable()->assertJsonPath('message', 'Este e-mail já tem conta. Clique em Já tenho conta e entre com sua senha.');
        $this->assertSame(0, DB::table('members')->count());
    }
}
