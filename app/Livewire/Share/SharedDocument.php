<?php

namespace App\Livewire\Share;

use App\Http\Controllers\SharedDocumentController;
use App\Models\Document;
use App\Models\DocumentActivity;
use App\Models\DocumentShare;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * The page someone outside the company lands on when they open a share link.
 *
 * No login, no application chrome, and nothing reachable from here but what
 * was shared. An expired or revoked link says so plainly rather than 404-ing,
 * so the recipient knows to ask for a new one instead of assuming the file is
 * gone.
 */
class SharedDocument extends Component
{
    public DocumentShare $share;

    public string $password = '';
    public bool $unlocked = false;

    public function mount(string $token): void
    {
        $share = DocumentShare::where('token', $token)
            ->with(['document.currentVersion', 'folder'])
            ->first();

        abort_unless($share, 404);

        $this->share = $share;
        $this->unlocked = ! $share->requiresPassword()
            || (bool) session(SharedDocumentController::unlockedKey($share));

        if ($this->unlocked && $share->isUsable()) {
            $this->recordAccess();
        }
    }

    public function unlock(): void
    {
        $this->validate(['password' => ['required', 'string']], [
            'password.required' => __('Enter the password to open this link.'),
        ]);

        // Brute force protection: the token is public, so the password is the
        // only thing in the way.
        $key = 'share-unlock:'.$this->share->id.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('password', __('Too many attempts. Try again in :seconds seconds.', [
                'seconds' => RateLimiter::availableIn($key),
            ]));

            return;
        }

        if (! $this->share->checkPassword($this->password)) {
            RateLimiter::hit($key, 300);

            $this->addError('password', __('That password is not right.'));

            return;
        }

        RateLimiter::clear($key);

        session([SharedDocumentController::unlockedKey($this->share) => true]);

        $this->unlocked = true;
        $this->password = '';

        $this->recordAccess();
    }

    /**
     * For a folder link: everything inside it, including its subfolders.
     *
     * @return Collection<int, Document>
     */
    public function documents(): Collection
    {
        if (! $this->share->isFolderShare() || ! $this->unlocked || ! $this->share->isUsable()) {
            return new Collection();
        }

        return Document::whereIn('folder_id', $this->share->folder->descendantIds())
            ->whereNotNull('current_version_id')
            // Internal documents are never handed out by a folder link: whoever
            // shared the folder cannot be expected to know everything in it.
            ->where('is_internal', false)
            ->with('folder')
            ->orderBy('name')
            ->get();
    }

    private function recordAccess(): void
    {
        $this->share->forceFill(['last_accessed_at' => now()])->save();

        DocumentActivity::record(
            DocumentActivity::SHARE_ACCESSED,
            [
                'document_id' => $this->share->document_id,
                'folder_id' => $this->share->folder_id,
                'share_id' => $this->share->id,
            ],
            ['action' => 'opened']
        );
    }

    public function render()
    {
        return view('livewire.share.shared-document', [
            'documents' => $this->documents(),
        ])->layout('components.layouts.guest');
    }
}
