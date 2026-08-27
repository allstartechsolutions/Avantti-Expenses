<?php

namespace App\Livewire\Concerns;

use App\Models\Approval;
use App\Models\Rfi;
use App\Services\Collaboration\CollaborationDistributor;

/**
 * Signing a document and posting it out — shared by the two detail screens.
 *
 * A component using this supplies `signableDocument()` and the two ability
 * names its area uses, so both guards are answered against this record's own
 * project rather than against anything the request supplied.
 */
trait SignsAndDistributes
{
    use AuthorizesAbility;

    /** Signature form. */
    public string $signerDocument = '';

    public string $artNumber = '';

    /** Distribution form. */
    public string $distributionNote = '';

    abstract protected function signableDocument(): Rfi|Approval;

    /** e.g. 'rfis' or 'approvals'. */
    abstract protected function areaKey(): string;

    protected function documentScope(): mixed
    {
        $document = $this->signableDocument();

        return $document->jobSite ?? $document->project;
    }

    public function getCanSignProperty(): bool
    {
        // Signing is part of putting your name to the record, so it follows
        // whoever may answer or respond on it — the same people who decide it.
        return $this->allowsAbility($this->areaKey().'.'.($this->areaKey() === 'rfis' ? 'answer' : 'respond'), $this->documentScope());
    }

    public function getCanDistributeProperty(): bool
    {
        return $this->allowsAbility($this->areaKey().'.distribute', $this->documentScope());
    }

    public function getCanExportProperty(): bool
    {
        return $this->allowsAbility($this->areaKey().'.export', $this->documentScope());
    }

    /**
     * Sign this document.
     *
     * The registration and the ART travel with the signature rather than being
     * read off the user later: they are what the signature *says*, and a
     * profile edited afterwards must not change what was signed.
     */
    public function signDocument(): void
    {
        $ability = $this->areaKey().'.'.($this->areaKey() === 'rfis' ? 'answer' : 'respond');

        $this->authorizeAbility($ability, $this->documentScope());

        $this->validate([
            'signerDocument' => 'nullable|string|max:255',
            'artNumber' => 'nullable|string|max:255',
        ]);

        $document = $this->signableDocument();

        $document->sign(
            auth()->user(),
            $this->signerDocument ?: null,
            $this->artNumber ?: null,
        );

        $document->logActivity(\App\Models\Collaboration\ActivityLogEntry::SIGNED);

        $this->reset(['signerDocument', 'artNumber']);
        $document->refresh();

        $this->dispatch('close-modal', 'document-sign');
        session()->flash($this->areaKey() === 'rfis' ? 'rfi_message' : 'approval_message', __('collaboration.message.signed'));
    }

    /** Post the document to everybody on its distribution list. */
    public function distributeDocument(CollaborationDistributor $distributor): void
    {
        $this->authorizeAbility($this->areaKey().'.distribute', $this->documentScope());

        $this->validate(['distributionNote' => 'nullable|string|max:2000']);

        $document = $this->signableDocument();

        if ($document->distribution()->count() === 0) {
            $this->addError('distributionNote', __('collaboration.help.nobody_distribution_list_there_nowhere'));

            return;
        }

        $result = $distributor->distribute($document, auth()->user(), $this->distributionNote ?: null);

        $this->reset('distributionNote');
        $document->refresh();

        $this->dispatch('close-modal', 'document-distribute');

        // Say what actually happened, including the part that did not work.
        $message = trans_choice('collaboration.count.sent_recipient_sent_recipients', $result['sent'], ['count' => $result['sent']]);

        if ($result['failed'] > 0) {
            $message .= ' '.trans_choice('collaboration.count.address_could_reached_addresses',
                $result['failed'],
                ['count' => $result['failed']],
            );
        }

        session()->flash($this->areaKey() === 'rfis' ? 'rfi_message' : 'approval_message', $message);
    }
}
