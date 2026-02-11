# User Profile Page

## Overview

Full-page Livewire component where authenticated users can update their profile information and change their password. Accessible via the sidebar Profile link.

## Files

| File | Purpose |
|------|---------|
| `app/Livewire/Profile/UserProfile.php` | Livewire component with `updateProfile()` and `updatePassword()` methods |
| `resources/views/livewire/profile/user-profile.blade.php` | Blade view with two card sections |

## Route

```php
Route::get('profile', UserProfile::class)->name('profile');
```

Located inside the `auth` middleware group in `routes/web.php`.

## Features

### Profile Information Card

- **Fields**: Name (required), Email (required, unique), Phone (optional)
- Validates email uniqueness excluding the current user
- Flash message on successful update

### Change Password Card

- **Fields**: Current Password, New Password, Confirm New Password
- Server-side validation: `current_password` rule, `confirmed`, `different:current_password`, plus `Password::min(8)->letters()->mixedCase()->symbols()`

#### Live Password Requirements Checklist (Alpine.js)

The checklist appears as soon as the user starts typing in the New Password field. Each requirement shows a green checkmark when met or a grey circle when not:

1. At least 8 characters
2. At least one lowercase letter
3. At least one uppercase letter
4. At least one symbol (!@#$%...)
5. Different from current password
6. Passwords match (new password === confirm password)

The "Update Password" button is disabled until all 6 requirements pass.

#### Post-Password Change Behavior

After a successful password update, the user is **logged out**, the session is invalidated, and the CSRF token is regenerated. The user is redirected to the login page to sign in with their new password.

## Sidebar Link

The Profile link in the sidebar user dropdown (`sidebar.blade.php` ~line 283) points to `route('profile')`.
