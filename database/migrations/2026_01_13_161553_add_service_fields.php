<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Services table - Ajouter les champs manquants
        Schema::table('services', function (Blueprint $table) {
            if (!Schema::hasColumn('services', 'auth_type')) {
                $table->string('auth_type')->default('none')->after('name');
            }
            if (!Schema::hasColumn('services', 'description')) {
                $table->text('description')->nullable()->after('auth_type');
            }
            if (!Schema::hasColumn('services', 'config_schema')) {
                $table->json('config_schema')->nullable()->after('description');
            }
        });

        // Actions table - Ajouter identifier et config_schema
        Schema::table('actions', function (Blueprint $table) {
            if (!Schema::hasColumn('actions', 'identifier')) {
                $table->string('identifier')->nullable()->after('name');
            }
            if (!Schema::hasColumn('actions', 'config_schema')) {
                $table->json('config_schema')->nullable()->after('description');
            }
        });

        // Reactions table - Ajouter identifier et config_schema
        Schema::table('reactions', function (Blueprint $table) {
            if (!Schema::hasColumn('reactions', 'identifier')) {
                $table->string('identifier')->nullable()->after('name');
            }
            if (!Schema::hasColumn('reactions', 'config_schema')) {
                $table->json('config_schema')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['auth_type', 'description', 'config_schema']);
        });

        Schema::table('actions', function (Blueprint $table) {
            $table->dropColumn(['identifier', 'config_schema']);
        });

        Schema::table('reactions', function (Blueprint $table) {
            $table->dropColumn(['identifier', 'config_schema']);
        });
    }
};