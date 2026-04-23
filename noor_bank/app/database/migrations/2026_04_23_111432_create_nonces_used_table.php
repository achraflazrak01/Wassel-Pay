<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nonces_used', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();           // UUID déjà utilisé
            $table->timestamp('received_at');
            $table->timestamps();
            
            $table->index('uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nonces_used');
    }
};
