<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (!Schema::hasColumn('services', 'created_at')) {
                $table->timestamps();
            }
        });
        
        Schema::table('actions', function (Blueprint $table) {
            if (!Schema::hasColumn('actions', 'created_at')) {
                $table->timestamps();
            }
        });
        
        Schema::table('reactions', function (Blueprint $table) {
            if (!Schema::hasColumn('reactions', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // Ne rien faire ici pour éviter de supprimer les données
    }
};