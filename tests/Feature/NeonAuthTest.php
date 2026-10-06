<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NeonAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        config(['facilitajud.demo' => false, 'facilitajud.auth_provider' => 'neon',
            'facilitajud.neon_url' => 'https://auth.example.test/neondb/auth']);
        $pair = sodium_crypto_sign_keypair();
        $this->secret = base64_encode(sodium_crypto_sign_secretkey($pair));
        $public = JWT::urlsafeB64Encode(sodium_crypto_sign_publickey($pair));
        Http::preventStrayRequests();
        Http::fake(['https://auth.example.test/neondb/auth/.well-known/jwks.json' => Http::response([
            'keys' => [['kty' => 'OKP', 'crv' => 'Ed25519', 'kid' => 'test-key', 'alg' => 'EdDSA', 'x' => $public]],
        ])]);
    }

    private function token(array $overrides = []): string
    {
        return JWT::encode(array_replace(['iss' => 'https://auth.example.test', 'aud' => 'https://auth.example.test',
            'sub' => 'neon-user-1', 'email' => 'advogado@example.test', 'name' => 'Ana Silva',
            'iat' => time(), 'exp' => time() + 900], $overrides), $this->secret, 'EdDSA', 'test-key');
    }

    public function test_a_verified_neon_token_creates_one_private_workspace_and_a_session(): void
    {
        $token = $this->token();
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/auth/neon/session')->assertOk();
        $this->get('/painel')->assertOk()->assertSee('Escritório de Ana Silva')->assertDontSee('Mariana Torres');
        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/auth/neon/session')->assertOk();
        $this->assertSame(1, DB::table('offices')->count());
        $this->assertSame(1, DB::table('members')->count());
        $this->post('/sair')->assertRedirect('/entrar');
        $this->get('/painel')->assertRedirect('/entrar');
    }

    public function test_invalid_issuer_audience_expiration_and_banned_users_are_rejected(): void
    {
        foreach ([['iss' => 'https://other.test'], ['aud' => 'https://other.test'], ['exp' => time() - 60], ['banned' => true]] as $claims) {
            $this->withHeader('Authorization', 'Bearer '.$this->token($claims))->postJson('/auth/neon/session')->assertUnauthorized();
        }
        $this->withHeader('Authorization', 'Bearer corrupted')->postJson('/auth/neon/session')->assertUnauthorized();
        $this->assertSame(0, DB::table('offices')->count());
    }
}
