{{--
    Running total of the amounts typed into the pay column of the contract
    payment tables, worked out in the browser so it moves with every keystroke.
    It sums every amount entered, not only the rows on screen: that is what
    processing / saving the batch will act on.
--}}
@php $enteredLabel = explode('|', __(':count payment entered|:count payments entered')); @endphp
<div class="text-right"
     x-data="{
        money: new Intl.NumberFormat(@js(str_replace('_', '-', config('app.locale'))), { style: 'currency', currency: @js(config('app.currency')) }),
        labels: @js($enteredLabel),
        get entered() {
            return Object.values($wire.payAmounts ?? {}).map(v => parseFloat(v)).filter(v => v > 0);
        },
        get total() {
            return Math.round(this.entered.reduce((sum, v) => sum + v, 0) * 100) / 100;
        },
        get countLabel() {
            const n = this.entered.length;
            return (n === 1 ? this.labels[0] : (this.labels[1] ?? this.labels[0])).replace(':count', n);
        },
     }">
    <div class="text-sm font-semibold"
         :class="total > 0 ? 'text-[#3F5189] dark:text-indigo-300' : 'text-slate-400 dark:text-slate-500'"
         x-text="money.format(total)"></div>
    <div class="text-xs text-slate-500 dark:text-slate-400" x-text="countLabel"></div>
</div>
