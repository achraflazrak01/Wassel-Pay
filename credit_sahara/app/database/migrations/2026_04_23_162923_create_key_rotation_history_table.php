<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('key_rotation_history', function (Blueprint $table) {
            $table->id();
            $table->string('old_key_fingerprint', 64);
            $table->string('new_key_fingerprint', 64);
            $table->string('bank', 50);
            $table->string('initiated_by', 100);      // Qui a initié (system ou admin)
            $table->integer('key_age_days');           // Âge de l'ancienne clé en jours
            $table->boolean('success')->default(true);
            $table->text('log_details')->nullable();
            $table->timestamp('rotated_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('key_rotation_history');
    }
};
