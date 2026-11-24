@props(['denominations' => [1000, 500, 200, 100, 50, 40, 20, 10, 5, 1], 'currency' => 'KES'])

<div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="mb-3 flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800">
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-100">
            Vault Cash Denominations
        </h4>
        <span class="text-xs font-semibold text-slate-500">
            Currency: <strong>{{ $currency }}</strong>
        </span>
    </div>

    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-5">
        @foreach($denominations as $val)
            <div class="flex flex-col gap-1 rounded-lg border border-slate-200 bg-slate-50/50 p-2 dark:border-slate-800 dark:bg-slate-800/40">
                <div class="text-xs font-bold text-slate-700 dark:text-slate-300">
                    {{ $currency }} {{ number_format($val) }}
                </div>
                <input
                    type="number"
                    name="denominations[{{ $val }}]"
                    min="0"
                    value="0"
                    class="h-7 w-full rounded border border-slate-300 bg-white px-2 font-mono text-xs font-bold focus:border-emerald-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900"
                />
            </div>
        @endforeach
    </div>
</div>
