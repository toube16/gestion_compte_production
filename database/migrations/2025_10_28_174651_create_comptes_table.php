<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Some cloud Postgres providers require disabling transactions for DDL-heavy migrations.
     */
    public $withinTransaction = false;
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comptes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 50)->unique(); // Limiter la longueur
            // Définir les valeurs possibles pour le type de compte
            $table->enum('type', ['courant', 'epargne', 'depot_terme']);
            $table->decimal('solde', 15, 2)->default(0);
            $table->decimal('solde_minimum', 15, 2)->default(0); // Pour découvert autorisé
            $table->string('devise', 3)->default('XOF');
            $table->enum('statut', ['actif', 'bloque', 'ferme'])->default('actif');
            $table->unsignedBigInteger('client_id');
            $table->timestamp('date_ouverture')->useCurrent(); // Date d'ouverture
            $table->timestamp('date_fermeture')->nullable(); // Date de fermeture
            
            // Clé étrangère
        $table->foreign('client_id')
            ->references('id')
            ->on('clients')
            ->onDelete('restrict'); // Meilleur que cascade pour les comptes
            
            $table->timestamps();
            $table->softDeletes(); // Pour garder l'historique
            
            // Index
            $table->index('numero');
            $table->index('type');
            $table->index('statut');
            $table->index('client_id');
            $table->index(['client_id', 'statut']); // Index composé
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comptes');
    }
};