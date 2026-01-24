<?php

namespace App\Livewire\Client;

use App\Models\Client;
use Livewire\Component;

class ClientShow extends Component
{
    public Client $client;

    public function mount(Client $client)
    {
        $this->client = $client;
    }

    public function render()
    {
        return view('livewire.client.client-show')
            ->layout('components.layouts.app');
    }
}
