<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_revocation_list', function (Blueprint $table) {
            $table->id();
            $table->string('key_fingerprint', 64)->unique();  // Empreinte SHA-256 de la clé
            $table->string('key_type', 20)->default('ECC_P256'); // Type de clé
            $table->string('bank', 50);                        // Banque propriétaire
            $table->timestamp('revoked_at');                   // Date de révocation
            $table->string('reason', 255);                     // Motif (rotation, compromission)
            $table->string('replaced_by', 64)->nullable();     // Nouvelle clé qui remplace
            $table->timestamp('created_at');
            $table->timestamp('expires_at');                   // Date d'expiration
        });
        
        // Index pour recherche rapide
        Schema::table('key_revocation_list', function (Blueprint $table) {
            $table->index('key_fingerprint');
            $table->index('revoked_at');
            $table->index('bank');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_revocation_list');
    }
};
