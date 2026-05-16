<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Validation\ValidationException;
use Kkiapay\Constants;
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

        $transaction = $this->fetchTransaction($transactionId, $publicKey, $privateKey, $secret);

        $status = data_get($transaction, 'status');
        if ($status !== STATUS::SUCCESS) {
            $message = $status === STATUS::PENDING
                ? 'Le paiement est encore en attente chez Kkiapay. Patientez quelques secondes puis réessayez.'
                : 'Le paiement Kkiapay n’a pas été confirmé.';

            throw ValidationException::withMessages([
                'payment' => $message,
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

    private function fetchTransaction(string $transactionId, string $publicKey, string $privateKey, string $secret): object
    {
        $client = new Client([
            'verify' => base_path('vendor/kkiapay/kkiapay-php/data/cacert.pem'),
            'connect_timeout' => 5,
            'timeout' => 12,
            'http_errors' => false,
        ]);

        $baseUrl = config('services.kkiapay.sandbox') ? Constants::SANDBOX_URL : Constants::BASE_URL;
        $lastStatusCode = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = $client->post($baseUrl.'/api/v1/transactions/status', [
                    'json' => ['transactionId' => $transactionId],
                    'headers' => [
                        'Accept' => 'application/json',
                        'X-API-KEY' => $publicKey,
                        'X-PRIVATE-KEY' => $privateKey,
                        'X-SECRET-KEY' => $secret,
                    ],
                ]);
            } catch (Throwable) {
                if ($attempt === 1) {
                    usleep(700000);
                    continue;
                }

                throw ValidationException::withMessages([
                    'payment' => 'Impossible de vérifier le paiement Kkiapay. Réessayez.',
                ]);
            }

            $lastStatusCode = $response->getStatusCode();
            $body = (string) $response->getBody();
            $transaction = json_decode($body);

            if ($lastStatusCode >= 200 && $lastStatusCode < 300 && is_object($transaction)) {
                return $transaction;
            }

            if ($attempt === 1 && $lastStatusCode >= 500) {
                usleep(700000);
                continue;
            }
        }

        throw ValidationException::withMessages([
            'payment' => $lastStatusCode === 404
                ? 'Transaction Kkiapay introuvable ou invalide.'
                : 'Impossible de vérifier le paiement Kkiapay. Réessayez.',
        ]);
    }
}
