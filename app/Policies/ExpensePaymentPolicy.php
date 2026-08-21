<?php

namespace App\Policies;

/**
 * An installment is governed by its expense: the same area, the same project or
 * job site. It carries no scope column itself, so it declares
 * `permissionScope()` on the model and the resolver walks the rest.
 */
class ExpensePaymentPolicy extends ModulePolicy
{
    protected string $area = 'expenses';
}
