<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spreadsheet_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('checksum', 64);
            $table->longText('contents');
            $table->json('mapping')->nullable();
            $table->string('sheet')->nullable();
            $table->unsignedInteger('header_row')->default(1);
            $table->timestamps();
            $table->unique(['office_id', 'checksum']);
        });
        Schema::create('work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spreadsheet_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('source_row');
            $table->foreignId('assigned_member_id')->nullable()->constrained('members');
            $table->string('title', 500);
            $table->string('process_number')->nullable();
            $table->text('context')->nullable();
            $table->timestampTz('due_at');
            $table->string('status')->default('Pendente');
            $table->text('note')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['spreadsheet_import_id', 'source_row']);
            $table->index(['office_id', 'assigned_member_id', 'due_at']);
        });
        Schema::create('work_item_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members');
            $table->string('actor');
            $table->string('status');
            $table->text('note');
            $table->timestampTz('created_at');
        });
        Schema::create('team_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invitations');
        Schema::dropIfExists('work_item_updates');
        Schema::dropIfExists('work_items');
        Schema::dropIfExists('spreadsheet_imports');
    }
};
