<?php

namespace App\Livewire\Client;

use App\Exceptions\CardPointeException;
use App\Models\Client;
use App\Models\ClientPaymentMethod;
use App\Models\Project;
use App\Services\CardPointeService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ClientShow extends Component
{
    public Client $client;
    public int $projectsCount = 0;

    // Delete modal
    public $showDeleteModal = false;
    public $deleteClientData = [];

    // Payment methods
    public array $paymentMethods = [];
    public bool $cardPointeConfigured = false;

    // Add card modal
    public bool $showAddCardModal = false;
    public string $cardToken = '';
    public string $cardName = '';
    public string $cardExpiry = '';
    public string $cardCvv = '';
    public string $cardZip = '';
    public string $cardError = '';

    // Edit card modal
    public bool $showEditCardModal = false;
    public ?int $editingCardId = null;
    public string $editCardName = '';
    public string $editExpiry = '';
    public string $editCardDisplayName = '';
    public string $editError = '';

    public function mount(Client $client)
    {
        $this->client = $client;
        $this->projectsCount = Project::where('client_id', $client->id)->count();
        $this->cardPointeConfigured = app(CardPointeService::class)->isConfigured();
        $this->loadPaymentMethods();
    }

    // Payment methods

    public function loadPaymentMethods(): void
    {
        $this->paymentMethods = $this->client->paymentMethods()
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ClientPaymentMethod $pm) => [
                'id' => $pm->id,
                'display_name' => $pm->getDisplayName(),
                'card_brand' => $pm->card_brand,
                'card_last_four' => $pm->card_last_four,
                'expiry' => $pm->expiry,
                'expiry_formatted' => $pm->getExpiryFormatted(),
                'is_default' => $pm->is_default,
            ])
            ->toArray();
    }

    public function openAddCardModal(): void
    {
        $this->resetAddCardFields();
        $this->showAddCardModal = true;
    }

    public function resetAddCardFields(): void
    {
        $this->cardToken = '';
        $this->cardName = '';
        $this->cardExpiry = '';
        $this->cardCvv = '';
        $this->cardZip = '';
        $this->cardError = '';
    }

    public function setCardToken(string $token): void
    {
        $this->cardToken = $token;
        $this->cardError = '';
    }

    public function addCard(): void
    {
        $this->validate([
            'cardName' => ['required', 'string', 'max:255'],
            'cardExpiry' => ['required', 'string', 'size:4'],
            'cardCvv' => ['required', 'string', 'min:3', 'max:4'],
            'cardZip' => ['required', 'string', 'max:10'],
        ], [
            'cardName.required' => 'Name on card is required.',
            'cardExpiry.required' => 'Expiration date is required.',
            'cardExpiry.size' => 'Expiration must be 4 digits (MMYY).',
            'cardCvv.required' => 'CVV is required.',
            'cardZip.required' => 'Billing zip code is required.',
        ]);

        if (empty($this->cardToken)) {
            $this->cardError = 'Please enter your card number.';
            return;
        }

        $service = app(CardPointeService::class);
        $existingProfileId = $this->client->cardpointe_profile_id;

        if ($existingProfileId) {
            // Add card to existing profile via profile endpoint
            try {
                $result = $service->addCardToProfile($existingProfileId, [
                    'account' => $this->cardToken,
                    'name' => $this->cardName,
                    'email' => $this->client->email,
                    'expiry' => $this->cardExpiry,
                ]);
            } catch (CardPointeException $e) {
                $this->cardError = $e->getMessage();
                return;
            }

            if (!$result['success']) {
                $this->cardError = $result['resptext'] ?: 'Failed to add card.';
                return;
            }

            $profileId = $existingProfileId;
            $acctId = $result['acctid'] ?? '1';
            $token = $result['token'] ?: $this->cardToken;
            $cardLastFour = substr(preg_replace('/\D/', '', $this->cardToken), -4);
        } else {
            // First card: $0 auth to validate + create profile
            try {
                $result = $service->authorize([
                    'amount' => '0',
                    'capture' => 'n',
                    'profile' => 'y',
                    'account' => $this->cardToken,
                    'name' => $this->cardName,
                    'expiry' => $this->cardExpiry,
                    'cvv2' => $this->cardCvv,
                    'postal' => $this->cardZip,
                ]);
            } catch (CardPointeException $e) {
                $this->cardError = $e->getMessage();
                return;
            }

            if (!$result['success']) {
                $this->cardError = $result['resptext'];
                return;
            }

            // Void the $0 auth
            if (!empty($result['retref'])) {
                try {
                    $service->void($result['retref']);
                } catch (CardPointeException $e) {
                    // Non-critical
                }
            }

            if (empty($result['profileid'])) {
                $this->cardError = 'Card was approved but no profile was created. Please try again.';
                return;
            }

            $this->client->update(['cardpointe_profile_id' => $result['profileid']]);

            $profileId = $result['profileid'];
            $acctId = $result['acctid'] ?? '1';
            $token = $result['token'];
            $cardLastFour = $result['card_last_four'];
        }

        // Save locally
        $isFirst = $this->client->paymentMethods()->count() === 0;

        ClientPaymentMethod::create([
            'client_id' => $this->client->id,
            'cardpointe_profile_id' => $profileId,
            'acctid' => $acctId,
            'card_last_four' => $cardLastFour,
            'card_brand' => $result['card_brand'] ?? null,
            'card_name' => $this->cardName,
            'expiry' => $this->cardExpiry ?: null,
            'token' => $token,
            'is_default' => $isFirst,
            'created_by' => Auth::id(),
        ]);

        $this->showAddCardModal = false;
        $this->resetAddCardFields();
        $this->loadPaymentMethods();
        session()->flash('message', __('Card added successfully!'));
    }

    public function openEditCardModal(int $id): void
    {
        $card = $this->client->paymentMethods()->find($id);
        if (!$card) {
            return;
        }

        $this->editingCardId = $id;
        $this->editCardName = $card->card_name ?? '';
        $this->editExpiry = $card->expiry ?? '';
        $this->editCardDisplayName = $card->getDisplayName();
        $this->editError = '';
        $this->showEditCardModal = true;
    }

    public function updateCard(): void
    {
        $this->validate([
            'editCardName' => ['required', 'string', 'max:255'],
            'editExpiry' => ['required', 'string', 'size:4'],
        ], [
            'editCardName.required' => 'Name on card is required.',
            'editExpiry.required' => 'Expiration date is required.',
            'editExpiry.size' => 'Expiration must be 4 digits (MMYY).',
        ]);

        $card = $this->client->paymentMethods()->find($this->editingCardId);
        if (!$card) {
            $this->editError = 'Card not found.';
            return;
        }

        $service = app(CardPointeService::class);

        try {
            $result = $service->updateProfile(
                $card->cardpointe_profile_id,
                $card->acctid,
                $card->token,
                [
                    'name' => $this->editCardName,
                    'email' => $this->client->email,
                    'expiry' => $this->editExpiry,
                ]
            );
        } catch (CardPointeException $e) {
            $this->editError = $e->getMessage();
            return;
        }

        if (!$result['success']) {
            $this->editError = $result['resptext'] ?: 'Failed to update card on the gateway.';
            return;
        }

        $card->update([
            'card_name' => $this->editCardName,
            'expiry' => $this->editExpiry,
        ]);

        $this->showEditCardModal = false;
        $this->editingCardId = null;
        $this->loadPaymentMethods();
        session()->flash('message', __('Card expiry updated successfully!'));
    }

    public function setDefaultCard(int $id): void
    {
        // Unset all defaults for this client
        $this->client->paymentMethods()->update(['is_default' => false]);

        // Set the selected card as default
        $this->client->paymentMethods()->where('id', $id)->update(['is_default' => true]);

        $this->loadPaymentMethods();
        session()->flash('message', __('Default payment method updated.'));
    }

    public function deleteCard(int $id): void
    {
        $card = $this->client->paymentMethods()->find($id);
        if (!$card) {
            return;
        }

        $wasDefault = $card->is_default;
        $card->delete(); // Soft delete

        // If the deleted card was default, set the next one as default
        if ($wasDefault) {
            $nextCard = $this->client->paymentMethods()->orderByDesc('created_at')->first();
            if ($nextCard) {
                $nextCard->update(['is_default' => true]);
            }
        }

        $this->loadPaymentMethods();
        session()->flash('message', __('Payment method removed.'));
    }

    // Delete client

    public function confirmDeleteClient()
    {
        if ($this->projectsCount > 0) {
            return;
        }

        $this->deleteClientData = [
            'name' => $this->client->company_name,
        ];

        $this->showDeleteModal = true;
        $this->dispatch('open-modal', 'delete-client-modal');
    }

    public function deleteClient()
    {
        // Re-check as a safety guard
        if (Project::where('client_id', $this->client->id)->exists()) {
            $this->cancelDeleteClient();
            return;
        }

        $this->client->delete();

        session()->flash('message', __('Client deleted successfully!'));
        return $this->redirect(route('clients.index'), navigate: true);
    }

    public function cancelDeleteClient()
    {
        $this->showDeleteModal = false;
        $this->deleteClientData = [];
        $this->dispatch('close-modal', 'delete-client-modal');
    }

    public function render()
    {
        $iframeUrl = $this->cardPointeConfigured ? app(CardPointeService::class)->getIframeUrl() : '';

        return view('livewire.client.client-show', [
            'iframeUrl' => $iframeUrl,
        ])
            ->layout('components.layouts.app');
    }
}
