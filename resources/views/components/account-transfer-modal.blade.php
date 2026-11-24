@props(['accounts' => [], 'currency' => 'KES'])

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="mb-4 border-b border-slate-100 pb-2 dark:border-slate-800">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Inter-Account Fund Transfer</h3>
    </div>

    <form method="POST" action="{{ route('payments-engine.transfers.initiate') }}" class="space-y-4">
        @csrf
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">Source Account</label>
                <select name="from_account_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs">
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name }} ({{ $currency }} {{ number_format($acc->balance, 2) }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">Destination Account</label>
                <select name="to_account_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs">
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">Amount</label>
                <input type="number" step="0.01" name="amount" required class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm font-bold" />
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">Charges / Fee</label>
                <input type="number" step="0.01" name="charges" value="0.00" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm font-bold" />
            </div>
        </div>

        <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700">
            Submit Transfer
        </button>
    </form>
</div>
