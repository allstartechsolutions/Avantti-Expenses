<?php

namespace Tests\Feature\Collaboration\Fixtures;

use App\Models\Concerns\Collaboration\BallInCourt;
use App\Models\Concerns\Collaboration\HasDistributionList;
use App\Models\Concerns\Collaboration\HasSequentialNumber;
use App\Models\Concerns\Collaboration\HasSignatures;
use App\Models\Concerns\Collaboration\LogsCollaborationActivity;
use App\Models\FileUpload;
use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A stand-in for an RFI or an approval, so the shared engine can be tested
 * before either of them exists.
 *
 * It carries exactly the columns the concerns require and nothing else — which
 * is itself worth knowing, because it is the contract phases 3 and 5 have to
 * meet. Its table is created by the test's setUp and dropped with the
 * database.
 */
class FakeDocument extends Model
{
    use BallInCourt;
    use HasDistributionList;
    use HasSequentialNumber;
    use HasSignatures;
    use LogsCollaborationActivity;

    protected $table = 'fake_documents';

    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(FileUpload::class, 'attachable');
    }

    public function documentType(): string
    {
        return 'rfi';
    }

    public function numberTokens(): array
    {
        return array_filter(['discipline' => $this->discipline]);
    }

    /** What the signer is putting their name to. Nothing that moves on its own. */
    public function signaturePayload(): array
    {
        return [
            'number' => $this->number,
            'subject' => $this->subject,
            'answer' => $this->answer,
            'status' => $this->status,
        ];
    }
}
