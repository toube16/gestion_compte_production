<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disable transactions for this migration (DDL + FKs on some hosts can fail inside transactions)
     */
    public $withinTransaction = false;
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->enum('type', ['depot', 'retrait', 'virement', 'transfert']);
            $table->decimal('montant', 15, 2);
            $table->string('devise', 3)->default('XOF');
            $table->text('description')->nullable();
            $table->enum('statut', ['en_attente', 'validee', 'rejete'])->default('en_attente');
            $table->timestamp('date_transaction');
            $table->unsignedBigInteger('compte_id');
            $table->foreign('compte_id')->references('id')->on('comptes')->onDelete('cascade');
            $table->unsignedBigInteger('compte_destination_id')->nullable();
            $table->foreign('compte_destination_id')->references('id')->on('comptes')->onDelete('set null');
            $table->timestamps();

            $table->index('reference');
            $table->index('type');
            $table->index('statut');
            $table->index('date_transaction');
            $table->index('compte_id');
            $table->index('compte_destination_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
