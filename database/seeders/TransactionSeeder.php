<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transaction;
use App\Models\Compte;

class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $comptes = Compte::all();

        // Étape 1 : initialiser le solde des comptes
        foreach ($comptes as $compte) {
            $compte->solde = 100000; // solde initial, exemple 100 000 XOF
            $compte->save();
        }

        // Étape 2 : créer des transactions
        Transaction::factory(50)->make()->each(function ($transaction) use ($comptes) {
            // choisir un compte au hasard
            $compte = $comptes->random();

            $transaction->compte_id = $compte->id;

            // Si retrait et solde insuffisant, ajuster le montant
            if ($transaction->type === 'retrait' && $transaction->montant > $compte->solde) {
                $transaction->montant = $compte->solde;
            }

            // Enregistrer la transaction
            $transaction->save();

            // Mettre à jour le solde du compte
            if ($transaction->type === 'depot') {
                $compte->solde += $transaction->montant;
            } else {
                $compte->solde -= $transaction->montant;
            }
            $compte->save();
        });
    }
}
