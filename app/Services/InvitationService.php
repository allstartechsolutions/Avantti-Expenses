<?php

namespace App\Services;

use App\Enums\AccessScope;
use App\Enums\MembershipStatus;
use App\Enums\UserStatus;
use App\Mail\InvitationMail;
use App\Models\JobSite;
use App\Models\Membership;
use App\Models\PermissionAudit;
use App\Models\PermissionTemplate;
use App\Models\Project;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Inviting somebody who has no login yet — a new member of staff, or an
 * outsider being given access to one project.
 *
 * The invitation carries everything the acceptance needs: the role, whether
 * they are a guest, and the memberships to create. Nothing is written to
 * `users` until the person accepts, so an invitation that is never taken up
 * leaves no account behind.
 *
 * Only the SHA-256 of the token is stored. The plain token exists once, in the
 * e-mail.
 */
class InvitationService
{
    /**
     * @param  array{scope: Project|JobSite, abilities: array, template_id: ?int, title: ?string, can_see_money: bool, approval_limit: ?int}|null  $membership
     */
    public function invite(
        string $email,
        ?string $name,
        ?int $roleId,
        bool $isGuest,
        ?array $membership = null,
        ?int $invitedBy = null,
    ): UserInvitation {
        $token = UserInvitation::newToken();

        $invitation = UserInvitation::create([
            'email' => mb_strtolower(trim($email)),
            'name' => $name ?: null,
            'role_id' => $roleId,
            // A guest is confined by definition; staff invited to a project
            // start confined too, and an administrator can widen them later.
            'access_scope' => AccessScope::ASSIGNED,
            'is_guest' => $isGuest,
            'payload' => $membership ? [$this->encodeMembership($membership)] : [],
            'token_hash' => UserInvitation::hashToken($token),
            'expires_at' => now()->addDays((int) config('permissions.invitation_days', 14)),
            'invited_by' => $invitedBy ?? auth()->id(),
            'last_sent_at' => now(),
            'send_count' => 1,
        ]);

        $this->send($invitation, $token);

        PermissionAudit::record(
            subjectType: 'invitation',
            subjectId: $invitation->id,
            action: 'created',
            summary: __(':email invited', ['email' => $invitation->email])
                .($membership ? ' — '.$this->scopeName($membership['scope']) : ''),
            scopeable: $membership['scope'] ?? null,
        );

        return $invitation;
    }

    /** A fresh token every time, so an old link stops working. */
    public function resend(UserInvitation $invitation): void
    {
        $token = UserInvitation::newToken();

        $invitation->update([
            'token_hash' => UserInvitation::hashToken($token),
            'expires_at' => now()->addDays((int) config('permissions.invitation_days', 14)),
            'last_sent_at' => now(),
            'send_count' => $invitation->send_count + 1,
        ]);

        $this->send($invitation, $token);
    }

    public function revoke(UserInvitation $invitation): void
    {
        $invitation->update(['revoked_at' => now()]);

        PermissionAudit::record(
            subjectType: 'invitation',
            subjectId: $invitation->id,
            action: 'revoked',
            summary: __('Invitation to :email withdrawn', ['email' => $invitation->email]),
        );
    }

    /**
     * Turn an accepted invitation into a user and their memberships.
     */
    public function accept(UserInvitation $invitation, string $name, string $password): User
    {
        return DB::transaction(function () use ($invitation, $name, $password) {
            $user = User::create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => Hash::make($password),
                'role_id' => $invitation->role_id,
                'status' => UserStatus::ACTIVE,
                'access_scope' => $invitation->access_scope,
                'is_guest' => $invitation->is_guest,
                'email_verified_at' => now(),
            ]);

            foreach ($invitation->payload ?? [] as $entry) {
                $this->createMembership($user, $entry, $invitation);
            }

            $invitation->update([
                'accepted_at' => now(),
                'accepted_user_id' => $user->id,
            ]);

            PermissionAudit::record(
                subjectType: 'invitation',
                subjectId: $invitation->id,
                action: 'accepted',
                summary: __(':email accepted their invitation', ['email' => $invitation->email]),
                subjectUserId: $user->id,
            );

            return $user;
        });
    }

    protected function createMembership(User $user, array $entry, UserInvitation $invitation): void
    {
        $scopeClass = $entry['scopeable_type'] ?? null;

        if (! in_array($scopeClass, [Project::class, JobSite::class], true)) {
            return;
        }

        $scope = $scopeClass::find($entry['scopeable_id'] ?? null);

        if (! $scope) {
            return;   // the project was deleted while the invitation sat unopened
        }

        $level = $scope instanceof JobSite ? 'job_site' : 'project';

        $membership = Membership::create([
            'user_id' => $user->id,
            'scopeable_type' => $scopeClass,
            'scopeable_id' => $scope->getKey(),
            'permission_template_id' => $entry['permission_template_id'] ?? null,
            'title' => $entry['title'] ?? null,
            'can_see_money' => (bool) ($entry['can_see_money'] ?? false),
            'approval_limit' => $entry['approval_limit'] ?? null,
            'status' => MembershipStatus::ACTIVE,
            'invited_by' => $invitation->invited_by,
            'invited_at' => $invitation->created_at,
            'accepted_at' => now(),
        ]);

        // Re-checked at acceptance, not trusted from the payload: the
        // catalogue may have changed since the invitation was sent.
        $membership->syncAbilities(
            AbilityCatalog::filter($entry['abilities'] ?? [], $level)
        );
    }

    protected function encodeMembership(array $membership): array
    {
        $scope = $membership['scope'];
        $level = $scope instanceof JobSite ? 'job_site' : 'project';

        return [
            'scopeable_type' => $scope::class,
            'scopeable_id' => $scope->getKey(),
            'permission_template_id' => $membership['template_id'] ?? null,
            'title' => $membership['title'] ?? null,
            'can_see_money' => (bool) ($membership['can_see_money'] ?? false),
            'approval_limit' => $membership['approval_limit'] ?? null,
            'abilities' => AbilityCatalog::filter($membership['abilities'] ?? [], $level),
        ];
    }

    protected function send(UserInvitation $invitation, string $token): void
    {
        Mail::to($invitation->email)->send(new InvitationMail($invitation, $token));
    }

    protected function scopeName(Project|JobSite $scope): string
    {
        return $scope instanceof JobSite
            ? (string) $scope->job_site_name
            : (string) $scope->project_name;
    }

    /** Where an accepted invitation should land somebody. */
    public function landingFor(User $user): string
    {
        $membership = $user->memberships()->with('scopeable')->first();

        if (! $membership || ! $membership->scopeable) {
            return route('dashboard');
        }

        return $membership->scopeable instanceof JobSite
            ? route('jobsites.overview', $membership->scopeable)
            : route('projects.overview', $membership->scopeable);
    }

    /** The templates a guest may be given, for the invite form. */
    public function guestTemplates(string $level)
    {
        return PermissionTemplate::forLevel($level)->forGuests()->orderBy('name')->get();
    }
}
