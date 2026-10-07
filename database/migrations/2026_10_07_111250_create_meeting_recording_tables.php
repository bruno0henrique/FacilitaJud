<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('version', 20);
            $table->timestampTz('accepted_at');
            $table->unique(['member_id', 'version']);
        });
        Schema::create('meeting_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('recording');
            $table->string('mime', 80);
            $table->string('consent_version', 20);
            $table->timestampTz('participants_confirmed_at');
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedBigInteger('size')->default(0);
            $table->text('notes')->nullable();
            $table->text('minutes')->nullable();
            $table->timestamps();
            $table->index(['office_id', 'appointment_id']);
        });
        Schema::create('meeting_audio_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recording_id')->constrained('meeting_recordings')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->unsignedInteger('size');
            $table->string('checksum', 64);
            $table->longText('contents');
            $table->unique(['recording_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_audio_chunks');
        Schema::dropIfExists('meeting_recordings');
        Schema::dropIfExists('meeting_consents');
    }
};
