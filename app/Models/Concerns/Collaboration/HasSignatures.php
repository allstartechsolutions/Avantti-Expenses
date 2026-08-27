<?php

namespace App\Models\Concerns\Collaboration;

use App\Models\Collaboration\Signature;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Lets a document be signed, and lets the signature be checked afterwards.
 *
 * The checking is the part that matters. A signature row on its own records
 * that somebody pressed a button; a signature row with a hash of what the
 * document said at that moment answers "is this still what was signed?".
 *
 * A model using this must implement `signaturePayload()`.
 */
trait HasSignatures
{
    public function signatures(): MorphMany
    {
        return $this->morphMany(Signature::class, 'signable')->orderBy('signed_at');
    }

    /**
     * The fields that are being signed, as a stable array.
     *
     * Include what the signer is putting their name to and nothing that moves
     * on its own — no `updated_at`, no counters, no derived totals. Adding a
     * field later invalidates existing signatures, which is correct: they did
     * not sign that field.
     *
     * @return array<string, mixed>
     */
    abstract public function signaturePayload(): array;

    /**
     * The hash of the document as it stands now.
     *
     * Keys are sorted so that two equal payloads hash equally regardless of
     * the order they were built in, and the encoding keeps unicode as unicode
     * so an accented name does not hash differently from one deploy to the
     * next.
     */
    public function signatureHash(): string
    {
        $payload = $this->signaturePayload();
        ksort($payload);

        return hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        ));
    }

    /**
     * The hash of the document as it is *stored*.
     *
     * A signature attests to the document everybody else can read, not to
     * unsaved changes sitting in one request's memory. The difference is not
     * hypothetical: a model just created does not carry the columns the
     * database defaulted, so hashing it in memory produces a hash that nothing
     * loaded afterwards can ever match.
     *
     * Takes a fresh copy rather than refreshing in place, so a caller holding
     * unsaved edits does not silently lose them.
     */
    public function storedSignatureHash(): string
    {
        // Memoised for the life of this instance. A page listing five
        // signatures asks five times, and each ask was a full re-read of the
        // document row; the stored record cannot change midway through
        // rendering one page.
        return $this->storedSignatureHash ??=
            ($this->exists ? ($this->fresh() ?? $this) : $this)->signatureHash();
    }

    /** Not a column: the memo for the call above. */
    protected ?string $storedSignatureHash = null;

    /**
     * Sign this document as somebody.
     *
     * `signer_name` is copied rather than read through the relation on
     * purpose: it is what the signature says, and it must not change because
     * the person later edited their profile or was deleted.
     */
    public function sign(
        User $user,
        ?string $signerDocument = null,
        ?string $artNumber = null,
        string $method = Signature::METHOD_DRAWN,
    ): Signature {
        return $this->signatures()->create([
            'user_id' => $user->id,
            'signer_name' => $user->name,
            'signer_document' => $signerDocument,
            'art_number' => $artNumber,
            'method' => $method,
            'signed_at' => now(),
            'ip_address' => request()?->ip(),
            'payload_hash' => $this->storedSignatureHash(),
        ]);
    }

    public function isSigned(): bool
    {
        return $this->signatures()->exists();
    }

    /**
     * Whether a signature still matches the document.
     *
     * False does not mean forgery — it usually means somebody with the right
     * to correct a closed record used it. Either way the screen must say so
     * rather than show a signature that no longer covers what is on it.
     */
    public function signatureIsIntact(Signature $signature): bool
    {
        return hash_equals($signature->payload_hash, $this->storedSignatureHash());
    }

    /** True when the document has signatures and every one of them still holds. */
    public function signaturesAreIntact(): bool
    {
        $signatures = $this->signatures()->get();

        return $signatures->isNotEmpty()
            && $signatures->every(fn (Signature $s) => $this->signatureIsIntact($s));
    }
}
