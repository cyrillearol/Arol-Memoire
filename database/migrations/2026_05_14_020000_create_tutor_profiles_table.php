<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('domain');
            $table->json('subjects');
            $table->decimal('hourly_rate', 10, 2);
            $table->text('bio');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_profiles');
    }
};
