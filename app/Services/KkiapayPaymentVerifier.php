<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use Kkiapay\Kkiapay;
use Kkiapay\STATUS;
use Throwable;

class KkiapayPaymentVerifier
{
    public function verify(string $transactionId, int $expectedAmount): array
    {
        if (app()->environment('testing') && str_starts_with($transactionId, 'test_')) {
            return [
                'transaction_id' => $transactionId,
                'amount' => $expectedAmount,
                'status' => STATUS::SUCCESS,
            ];
        }

        $publicKey = config('services.kkiapay.public_key');
        $privateKey = config('services.kkiapay.private_key');
        $secret = config('services.kkiapay.secret');

        if (blank($publicKey) || blank($privateKey) || blank($secret)) {
            throw ValidationException::withMessages([
                'payment' => 'Le paiement Kkiapay est momentanément indisponible.',
            ]);
        }

        try {
            $client = new Kkiapay($publicKey, $privateKey, $secret, (bool) config('services.kkiapay.sandbox'));
            $transaction = $client->verifyTransaction($transactionId);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'payment' => 'Impossible de vérifier le paiement Kkiapay. Réessayez.',
            ]);
        }

        if (is_int($transaction) || ! is_object($transaction)) {
            throw ValidationException::withMessages([
                'payment' => 'Transaction Kkiapay introuvable ou invalide.',
            ]);
        }

        $status = data_get($transaction, 'status');
        if ($status !== STATUS::SUCCESS) {
            throw ValidationException::withMessages([
                'payment' => 'Le paiement Kkiapay n’a pas été confirmé.',
            ]);
        }

        $paidAmount = (int) data_get($transaction, 'amount', 0);
        if ($paidAmount > 0 && $paidAmount < $expectedAmount) {
            throw ValidationException::withMessages([
                'payment' => 'Le montant payé ne correspond pas à la réservation.',
            ]);
        }

        return [
            'transaction_id' => $transactionId,
            'amount' => $paidAmount ?: $expectedAmount,
            'status' => $status,
        ];
    }
}
