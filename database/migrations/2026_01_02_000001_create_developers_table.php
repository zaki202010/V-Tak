<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Aman dijalankan walau tabel sudah pernah dibuat manual.
        if (Schema::hasTable('developers')) {
            return;
        }
        Schema::create('developers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('role', 100)->nullable();
            $t->string('photo')->nullable();
            $t->text('bio')->nullable();
            $t->string('skills', 500)->nullable();
            $t->text('experience')->nullable();
            $t->text('links')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('developers');
    }
};
