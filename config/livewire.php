<?php

/*
 * Only what differs from Livewire's own defaults (vendor/livewire/livewire/config/livewire.php);
 * the rest is merged in from there.
 */
return [
    /*
     * The layout a full-page component renders in when it names none. Livewire 4's
     * default is `layouts::app`, which this app does not have: every screen calls
     * ->layout('components.layouts.app') itself, but the Volt settings pages
     * (profile, password, two-factor, notifications, appearance, sign-in activity)
     * name none and failed with "No hint path defined for [layouts]" (found 5 Nov 2026).
     */
    'component_layout' => 'components.layouts.app',
];
