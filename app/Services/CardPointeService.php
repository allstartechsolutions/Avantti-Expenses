<?php

namespace App\Services;

use App\Exceptions\CardPointeException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CardPointeService
{
    protected string $env;
    protected string $merchantId;
    protected string $apiUser;
    protected string $apiPass;
    protected string $site;

    public function __construct()
    {
        $this->env = config('services.cardpointe.env', 'uat');
        $this->merchantId = config('services.cardpointe.merchant_id', '');
        $this->apiUser = config('services.cardpointe.api_user', '');
        $this->apiPass = config('services.cardpointe.api_pass', '');
        $this->site = config('services.cardpointe.site', 'fts-uat');
    }

    public function isConfigured(): bool
    {
        return !empty($this->merchantId) && !empty($this->apiUser) && !empty($this->apiPass);
    }

    public function getGatewayUrl(): string
    {
        if ($this->env === 'uat') {
            return 'https://fts-uat.cardconnect.com/cardconnect/rest/';
        }

        return "https://{$this->site}.cardconnect.com/cardconnect/rest/";
    }

    public function getIframeUrl(): string
    {
        if ($this->env === 'uat') {
            $base = 'https://fts-uat.cardconnect.com/itoke/ajax-tokenizer.html';
        } else {
            $base = "https://{$this->site}.cardconnect.com/itoke/ajax-tokenizer.html";
        }

        $params = http_build_query([
            'enhancedresponse' => 'true',
            'css' => 'body{font-family:ui-sans-serif,system-ui,sans-serif;margin:0}input{border:1px solid #cbd5e1;border-radius:0.5rem;padding:0.5rem 0.75rem;font-size:0.875rem;width:100%;box-sizing:border-box;outline:none}input:focus{border-color:#3F5189;box-shadow:0 0 0 1px #3F5189}',
        ]);

        return "{$base}?{$params}";
    }

    /**
     * Authorize (and capture) a payment.
     *
     * @throws CardPointeException
     */
    public function authorize(array $params): array
    {
        $payload = array_merge([
            'merchid' => $this->merchantId,
            'capture' => 'y',
            'ecomind' => 'E',
        ], $params);

        try {
            $response = Http::withBasicAuth($this->apiUser, $this->apiPass)
                ->timeout(30)
                ->put($this->getGatewayUrl() . 'auth', $payload);

            if (!$response->successful()) {
                Log::error('CardPointe authorize failed', [
                    'url' => $this->getGatewayUrl() . 'auth',
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new CardPointeException('Gateway error (HTTP ' . $response->status() . '): ' . ($response->body() ?: 'No response body. Check API credentials and gateway URL.'));
            }

            $data = $response->json();

            Log::info('CardPointe authorize response', [
                'respstat' => $data['respstat'] ?? null,
                'respcode' => $data['respcode'] ?? null,
                'resptext' => $data['resptext'] ?? null,
                'respproc' => $data['respproc'] ?? null,
                'amount' => $data['amount'] ?? null,
                'retref' => $data['retref'] ?? null,
            ]);

            return $this->normalizeAuthResponse($data);
        } catch (CardPointeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('CardPointe authorize exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new CardPointeException('Unable to connect to payment gateway. Please try again.');
        }
    }

    /**
     * Void a previous authorization.
     *
     * @throws CardPointeException
     */
    public function void(string $retref): array
    {
        try {
            $response = Http::withBasicAuth($this->apiUser, $this->apiPass)
                ->timeout(30)
                ->put($this->getGatewayUrl() . 'void', [
                    'merchid' => $this->merchantId,
                    'retref' => $retref,
                ]);

            if (!$response->successful()) {
                Log::error('CardPointe void failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new CardPointeException('Failed to void the transaction on the payment gateway.');
            }

            $data = $response->json();

            return [
                'success' => ($data['authcode'] ?? '') === 'REVERS',
                'respstat' => $data['respstat'] ?? '',
                'resptext' => $data['resptext'] ?? '',
                'retref' => $data['retref'] ?? $retref,
            ];
        } catch (CardPointeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('CardPointe void exception', [
                'message' => $e->getMessage(),
            ]);
            throw new CardPointeException('Unable to connect to payment gateway for void.');
        }
    }

    /**
     * Refund a captured transaction.
     *
     * @throws CardPointeException
     */
    public function refund(string $retref, int $amountCents): array
    {
        try {
            $response = Http::withBasicAuth($this->apiUser, $this->apiPass)
                ->timeout(30)
                ->put($this->getGatewayUrl() . 'refund', [
                    'merchid' => $this->merchantId,
                    'retref' => $retref,
                    'amount' => (string) $amountCents,
                ]);

            if (!$response->successful()) {
                Log::error('CardPointe refund failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new CardPointeException('Failed to refund the transaction on the payment gateway.');
            }

            $data = $response->json();

            return [
                'success' => ($data['respstat'] ?? '') === 'A',
                'respstat' => $data['respstat'] ?? '',
                'resptext' => $data['resptext'] ?? '',
                'retref' => $data['retref'] ?? $retref,
                'amount' => $data['amount'] ?? (string) $amountCents,
            ];
        } catch (CardPointeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('CardPointe refund exception', [
                'message' => $e->getMessage(),
            ]);
            throw new CardPointeException('Unable to connect to payment gateway for refund.');
        }
    }

    /**
     * Get a stored profile/card.
     */
    public function getProfile(string $profileId, string $acctId): ?array
    {
        try {
            $response = Http::withBasicAuth($this->apiUser, $this->apiPass)
                ->timeout(15)
                ->get($this->getGatewayUrl() . "profile/{$profileId}/{$acctId}/{$this->merchantId}");

            if (!$response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('CardPointe getProfile exception', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Add a card to an existing profile.
     *
     * @throws CardPointeException
     */
    public function addCardToProfile(string $profileId, array $data): array
    {
        try {
            $payload = array_merge([
                'merchid' => $this->merchantId,
                'profile' => $profileId,
            ], $data);

            $response = Http::withBasicAuth($this->apiUser, $this->apiPass)
                ->timeout(30)
                ->put($this->getGatewayUrl() . 'profile', $payload);

            if (!$response->successful()) {
                Log::error('CardPointe addCardToProfile failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new CardPointeException('Gateway error (HTTP ' . $response->status() . '): ' . ($response->body() ?: 'No response body.'));
            }

            $result = $response->json();

            return [
                'success' => ($result['respstat'] ?? '') === 'A',
                'resptext' => $result['resptext'] ?? '',
                'profileid' => $result['profileid'] ?? $profileId,
                'acctid' => $result['acctid'] ?? null,
                'token' => $result['token'] ?? '',
            ];
        } catch (CardPointeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('CardPointe addCardToProfile exception', [
                'message' => $e->getMessage(),
            ]);
            throw new CardPointeException('Unable to connect to payment gateway to add card.');
        }
    }

    /**
     * Update an existing card on a profile (e.g. expiry).
     *
     * @throws CardPointeException
     */
    public function updateProfile(string $profileId, string $acctId, string $account, array $data): array
    {
        try {
            $payload = array_merge([
                'merchid' => $this->merchantId,
                'profile' => $profileId . '/' . $acctId,
                'account' => $account,
            ], $data);

            $response = Http::withBasicAuth($this->apiUser, $this->apiPass)
                ->timeout(30)
                ->put($this->getGatewayUrl() . 'profile', $payload);

            if (!$response->successful()) {
                Log::error('CardPointe updateProfile failed', [
                    'url' => $this->getGatewayUrl() . 'profile',
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'payload_keys' => array_keys($payload),
                ]);
                throw new CardPointeException('Gateway error (HTTP ' . $response->status() . '): ' . ($response->body() ?: 'No response body.'));
            }

            $result = $response->json();

            return [
                'success' => ($result['respstat'] ?? '') === 'A',
                'resptext' => $result['resptext'] ?? '',
            ];
        } catch (CardPointeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('CardPointe updateProfile exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new CardPointeException('Update profile error: ' . $e->getMessage());
        }
    }

    /**
     * Delete a stored profile/card.
     */
    public function deleteProfile(string $profileId, string $acctId): bool
    {
        try {
            $response = Http::withBasicAuth($this->apiUser, $this->apiPass)
                ->timeout(15)
                ->delete($this->getGatewayUrl() . "profile/{$profileId}/{$acctId}/{$this->merchantId}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('CardPointe deleteProfile exception', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function normalizeAuthResponse(array $data): array
    {
        $respstat = $data['respstat'] ?? 'C';
        $token = $data['token'] ?? '';

        // Parse card last four from token (last 4 digits before any non-digit chars)
        $cardLastFour = '';
        if (!empty($token)) {
            $cardLastFour = substr(preg_replace('/\D/', '', $token), -4);
        }
        if (!empty($data['account'])) {
            $acct = preg_replace('/\D/', '', $data['account']);
            if (strlen($acct) >= 4) {
                $cardLastFour = substr($acct, -4);
            }
        }

        // Determine card brand
        $cardBrand = null;
        if (!empty($data['bintype'])) {
            $cardBrand = $data['bintype'];
        }

        if ($respstat === 'B') {
            return [
                'success' => false,
                'retref' => $data['retref'] ?? '',
                'authcode' => $data['authcode'] ?? '',
                'respstat' => $respstat,
                'respcode' => $data['respcode'] ?? '',
                'resptext' => 'Please try again.',
                'token' => $token,
                'profileid' => $data['profileid'] ?? null,
                'acctid' => $data['acctid'] ?? null,
                'card_last_four' => $cardLastFour,
                'card_brand' => $cardBrand,
                'amount' => $data['amount'] ?? '0',
            ];
        }

        return [
            'success' => $respstat === 'A',
            'retref' => $data['retref'] ?? '',
            'authcode' => $data['authcode'] ?? '',
            'respstat' => $respstat,
            'respcode' => $data['respcode'] ?? '',
            'resptext' => $data['resptext'] ?? 'Transaction declined.',
            'token' => $token,
            'profileid' => $data['profileid'] ?? null,
            'acctid' => $data['acctid'] ?? null,
            'card_last_four' => $cardLastFour,
            'card_brand' => $cardBrand,
            'amount' => $data['amount'] ?? '0',
        ];
    }
}
