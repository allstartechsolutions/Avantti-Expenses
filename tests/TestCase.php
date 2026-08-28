<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Areas declared in the catalogue whose permission pass is not finished.
     *
     * `swept => false` used to mean "falls back to the old role checks"; that
     * branch was deleted at F2 and the flag now only marks an area *not
     * enforced yet* in the permission matrix, so nobody hands out a grant that
     * does nothing. A module built after the bridge came out still spends a few
     * phases here: its abilities exist so the screens can be written against
     * them, and it flips once every action of it is guarded and filtered.
     *
     * The invariant the permission suite guards is "nothing is unswept by
     * accident", so the tests assert against this list rather than against an
     * empty array. `rfis` flipped at the end of phase 4 of
     * docs/RFI-Submittals-modules.md and `approvals` at the end of phase 5.
     *
     * `assignment-defaults` is here from phase 1 of
     * docs/procurement-assignment-plan.md: the panels are guarded and the
     * defaults resolve, but the requisition and quotation screens that consume
     * them arrive in phases 2 and 4, so the area is not finished being spent.
     * It flips at phase 7.
     */
    protected const AREAS_UNDER_CONSTRUCTION = [
        'assignment-defaults',
    ];
}
