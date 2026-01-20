<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('actions', function (Blueprint $table) {
            if (!Schema::hasColumn('actions', 'config')) {
                $table->json('config')->nullable()->after('description');
            }
        });
        
        Schema::table('reactions', function (Blueprint $table) {
            if (!Schema::hasColumn('reactions', 'config')) {
                $table->json('config')->nullable()->after('description');
            }
        });
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