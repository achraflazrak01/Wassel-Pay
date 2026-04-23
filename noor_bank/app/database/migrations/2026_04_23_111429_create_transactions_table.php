<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();           // Anti-rejeu
            $table->string('sender_bank', 50);              // Banque émettrice
            $table->text('encrypted_data');                 // Données chiffrées
            $table->text('signature');                      // Signature ECDSA
            $table->text('encrypted_session_key');          // Clé AES chiffrée
            $table->string('nonce', 100);                   // Nonce AES-GCM
            $table->string('tag', 100);                     // Tag d'authentification
            $table->enum('status', ['pending', 'received', 'processed', 'rejected'])->default('pending');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            
            $table->index('uuid');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
