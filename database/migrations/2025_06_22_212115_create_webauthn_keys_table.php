<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webauthn_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name'); // User-friendly name for the key
            $table->string('credential_id'); // Base64 encoded credential ID
            $table->text('public_key'); // Base64 encoded public key
            $table->unsignedInteger('counter')->default(0); // Signature counter
            $table->string('aaguid')->nullable(); // Authenticator AAGUID
            $table->json('transports')->nullable(); // Supported transports ['usb', 'nfc', 'ble', 'internal']
            $table->boolean('backup_eligible')->default(false); // Can be backed up
            $table->boolean('backup_state')->default(false); // Is backed up
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique('credential_id');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webauthn_keys');
    }
};
