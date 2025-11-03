<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ajouter la colonne deleted_at seulement si elle n'existe pas (sécurité pour fresh/multiple runs)
        if (!Schema::hasColumn('comptes', 'deleted_at')) {
            Schema::table('comptes', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comptes', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
