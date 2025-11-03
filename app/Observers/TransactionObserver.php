<?php

namespace App\Observers;

use App\Jobs\SendTransactionSms;
use App\Jobs\SyncTransactionToNeon;
use App\Models\Transaction;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\AccountBlockedException;
use Illuminate\Support\Facades\Log;

/**
 * Observer pour valider les règles métier des transactions
 * S'exécute automatiquement lors des opérations CRUD sur les transactions
 */
class TransactionObserver
{
    /**
     * Handle the Transaction "creating" event.
     * Valide les règles métier avant la création de la transaction
     */
    public function creating(Transaction $transaction): void
    {
        $this->validateTransactionRules($transaction);
    }

    /**
     * Handle the Transaction "created" event.
     * Déclenche les actions post-création (sync Neon, SMS)
     */
    public function created(Transaction $transaction): void
    {
        // Synchroniser vers Neon DB (découplé)
        SyncTransactionToNeon::dispatch($transaction);

        // Envoyer SMS de notification (découplé)
        SendTransactionSms::dispatch($transaction);

        Log::info('Transaction créée et jobs déclenchés', [
            'transaction_id' => $transaction->id,
            'reference' => $transaction->reference,
            'type' => $transaction->type,
            'montant' => $transaction->montant,
        ]);
    }

    /**
     * Valide les règles métier de la transaction
     */
    private function validateTransactionRules(Transaction $transaction): void
    {
        $compte = $transaction->compte()->withoutGlobalScopes()->first();

        // Règle 1: Le compte doit être actif et non bloqué
        if ($compte->statut !== 'actif' || $compte->is_blocked) {
            throw new AccountBlockedException("Le compte {$compte->numero} n'est pas actif ou est bloqué.");
        }

        // Règle 2: Pour les retraits, vérifier la disponibilité du solde
        if ($transaction->type === 'retrait') {
            if (!$compte->hasAvailableBalance($transaction->montant)) {
                throw new InsufficientFundsException(
                    "Solde insuffisant. Solde disponible: {$compte->solde} {$compte->devise}"
                );
            }
        }

        // Règle 3: Vérifier que le montant est positif
        if ($transaction->montant <= 0) {
            throw new \InvalidArgumentException("Le montant de la transaction doit être positif.");
        }

        // Règle 4: Pour les dépôts, vérifier le plafond (exemple: 1M FCFA max par dépôt)
        if ($transaction->type === 'depot' && $transaction->montant > 1000000) {
            throw new \InvalidArgumentException("Le montant maximum pour un dépôt est de 1.000.000 FCFA.");
        }

        // Règle 5: Vérifier la devise (doit correspondre à celle du compte)
        if ($transaction->devise !== $compte->devise) {
            throw new \InvalidArgumentException("La devise de la transaction doit correspondre à celle du compte ({$compte->devise}).");
        }

        Log::info('Règles métier validées pour la transaction', [
            'transaction_type' => $transaction->type,
            'montant' => $transaction->montant,
            'compte_id' => $compte->id,
            'solde_disponible' => $compte->solde,
        ]);
    }
}
