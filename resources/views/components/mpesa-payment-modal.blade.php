@props(['amount' => 0, 'currency' => 'KES'])

<div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="mb-4 border-b border-slate-100 pb-2 dark:border-slate-800">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">M-Pesa STK Express Checkout</h3>
    </div>

    <div class="space-y-4">
        <div class="rounded-xl bg-slate-50 p-3 text-center dark:bg-slate-800/60">
            <span class="text-xs text-slate-500">Amount to Pay</span>
            <div class="text-xl font-black text-slate-900 dark:text-white">
                {{ $currency }} {{ number_format($amount, 2) }}
            </div>
        </div>

        <div>
            <label class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300">Customer Phone Number</label>
            <input
                type="text"
                name="phone_number"
                placeholder="2547XXXXXXXX"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm font-bold focus:border-emerald-500 focus:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            />
        </div>

        <button
            type="submit"
            class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-700"
        >
            Send STK Push Prompt
        </button>
    </div>
</div>
