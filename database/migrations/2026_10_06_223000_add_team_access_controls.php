<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('responsibilities')->nullable();
            $table->json('permissions');
            $table->timestamps();
            $table->unique(['office_id', 'name']);
        });
        foreach (['members', 'team_invitations'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name): void {
                if ($name === 'members') {
                    $table->string('account_type')->default('associate');
                }
                $table->foreignId('category_id')->nullable()->constrained('team_categories')->nullOnDelete();
                $table->text('responsibilities')->nullable();
                $table->json('permissions')->nullable();
            });
        }
        DB::table('members')->whereIn('role', ['Administrador(a)', 'Administradora', 'Administrador'])->update(['account_type' => 'admin']);
        foreach (['legal_cases', 'tasks', 'appointments'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('assigned_member_id')->nullable()->constrained('members')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['legal_cases', 'tasks', 'appointments'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('assigned_member_id');
            });
        }
        foreach (['members', 'team_invitations'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name): void {
                $table->dropConstrainedForeignId('category_id');
                $table->dropColumn(['responsibilities', 'permissions']);
                if ($name === 'members') {
                    $table->dropColumn('account_type');
                }
            });
        }
        Schema::dropIfExists('team_categories');
    }
};
