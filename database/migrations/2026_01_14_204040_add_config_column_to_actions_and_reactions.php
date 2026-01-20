<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Ajouter config à actions
        if (!Schema::hasColumn('actions', 'config')) {
            Schema::table('actions', function (Blueprint $table) {
                $table->json('config')->nullable()->after('description');
            });
        }
        
        // Ajouter config à reactions
        if (!Schema::hasColumn('reactions', 'config')) {
            Schema::table('reactions', function (Blueprint $table) {
                $table->json('config')->nullable()->after('description');
            });
        }
    }

    public function down()
    {
        Schema::table('actions', function (Blueprint $table) {
            $table->dropColumn('config');
        });
        
        Schema::table('reactions', function (Blueprint $table) {
            $table->dropColumn('config');
        });
    }
};