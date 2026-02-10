<?php

namespace App\Livewire\Estimate;

use App\Models\Estimate;
use Livewire\Component;

class EstimateShow extends Component
{
    public Estimate $estimate;

    public function mount(Estimate $estimate)
    {
        $this->estimate = $estimate->load(['client', 'project', 'jobSite', 'items', 'createdBy']);
    }

    public function markAsSent()
    {
        if (!$this->estimate->isDraft()) {
            session()->flash('error', 'Only draft estimates can be marked as sent.');
            return;
        }

        $this->estimate->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->refreshEstimate();
        session()->flash('message', 'Estimate marked as sent!');
    }

    public function markAsAccepted()
    {
        if (!$this->estimate->isSent()) {
            session()->flash('error', 'Only sent estimates can be accepted.');
            return;
        }

        $this->estimate->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        $this->refreshEstimate();
        session()->flash('message', 'Estimate marked as accepted!');
    }

    public function markAsDeclined()
    {
        if (!$this->estimate->isSent()) {
            session()->flash('error', 'Only sent estimates can be declined.');
            return;
        }

        $this->estimate->update([
            'status' => 'declined',
            'declined_at' => now(),
        ]);

        $this->refreshEstimate();
        session()->flash('message', 'Estimate marked as declined.');
    }

    public function deleteEstimate()
    {
        if (!$this->estimate->canBeEdited()) {
            session()->flash('error', 'Only draft or sent estimates can be deleted.');
            return;
        }

        $this->estimate->items()->delete();
        $this->estimate->delete();

        session()->flash('message', 'Estimate deleted successfully!');

        return redirect()->route('estimates.index');
    }

    protected function refreshEstimate()
    {
        $this->estimate = $this->estimate->fresh(['client', 'project', 'jobSite', 'items', 'createdBy']);
    }

    public function render()
    {
        return view('livewire.estimate.estimate-show')
            ->layout('components.layouts.app');
    }
}
