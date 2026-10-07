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
        config(['facilitajud.demo' => false, 'facilitajud.auth_provider' => 'neon', 'facilitajud.neon_jwks' => null,
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

    public function test_only_the_configured_admin_receives_presentation_records(): void
    {
        config(['facilitajud.presentation_email' => 'advogado@example.test']);
        $this->withHeader('Authorization', 'Bearer '.$this->token())->postJson('/auth/neon/session')->assertOk();
        $office = DB::table('members')->where('provider_id', 'neon-user-1')->value('office_id');
        $this->assertSame(120, DB::table('work_items')->where('office_id', $office)->count());
        $this->withHeader('Authorization', 'Bearer '.$this->token())->postJson('/auth/neon/session')->assertOk();
        $this->assertSame(120, DB::table('work_items')->where('office_id', $office)->count());
        $this->withHeader('Authorization', 'Bearer '.$this->token(['sub' => 'another-admin', 'email' => 'other@example.test']))->postJson('/auth/neon/session')->assertOk();
        $other = DB::table('members')->where('provider_id', 'another-admin')->value('office_id');
        $this->assertSame(0, DB::table('work_items')->where('office_id', $other)->count());
    }

    public function test_invited_employee_joins_the_admin_office_without_receiving_admin_permissions(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->token())->postJson('/auth/neon/session')->assertOk();
        $office = DB::table('members')->where('provider_id', 'neon-user-1')->value('office_id');
        $category = $this->postJson('/api/v1/team/categories', ['name' => 'Assistente jurídico', 'responsibilities' => 'Conferir processos', 'permissions' => ['prazos.view']])->assertOk()->json('id');
        $link = $this->postJson('/api/v1/team/invite', ['email' => 'staff@example.test', 'name' => 'Funcionário convidado', 'category_id' => $category, 'responsibilities' => 'Conferir custas'])->assertOk()->json('url');
        parse_str(parse_url($link, PHP_URL_QUERY), $query);
        $this->withHeader('Authorization', 'Bearer '.$this->token(['sub' => 'staff-user', 'email' => 'other@example.test']))->postJson('/auth/neon/session', ['invitation' => $query['convite']])->assertForbidden();
        $this->withHeader('Authorization', 'Bearer '.$this->token(['sub' => 'staff-user', 'email' => 'staff@example.test']))->postJson('/auth/neon/session', ['invitation' => $query['convite']])->assertOk();
        $this->assertSame($office, DB::table('members')->where('provider_id', 'staff-user')->value('office_id'));
        $this->assertSame(1, DB::table('offices')->count());
        $this->assertDatabaseHas('members', ['provider_id' => 'staff-user', 'account_type' => 'associate', 'category_id' => $category, 'responsibilities' => 'Conferir custas']);
        $this->postJson('/api/v1/team/invite', ['email' => 'another@example.test', 'name' => 'Outra pessoa'])->assertForbidden();
    }
}
