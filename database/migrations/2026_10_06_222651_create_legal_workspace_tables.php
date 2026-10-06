<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('display_name');
            $table->boolean('reminders')->default(true);
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->string('provider_id')->nullable()->unique();
            $table->string('name');
            $table->string('email');
            $table->string('role')->default('Advogado(a)');
            $table->timestamps();
        });
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('legal_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('number')->nullable();
            $table->string('court');
            $table->string('status')->default('Em andamento');
            $table->string('responsible')->default('Helena Souza');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['office_id', 'number']);
        });
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_case_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('context')->nullable();
            $table->timestampTz('due_at');
            $table->string('priority')->default('Média');
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
            $table->index(['office_id', 'completed_at', 'due_at']);
        });
        Schema::create('deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_case_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->timestampTz('due_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
            $table->index(['office_id', 'completed_at', 'due_at']);
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_case_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('kind')->default('Reunião');
            $table->string('location');
            $table->timestampTz('starts_at');
            $table->timestamps();
        });
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legal_case_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('path');
            $table->string('mime');
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->string('actor');
            $table->string('kind')->default('task');
            $table->timestampTz('created_at');
        });
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('outgoing')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['messages', 'activities', 'documents', 'appointments', 'deadlines', 'tasks', 'legal_cases', 'clients', 'members', 'offices'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
