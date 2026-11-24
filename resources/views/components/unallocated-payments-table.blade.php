@props(['payments' => [], 'currency' => 'KES'])

<div class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="border-b border-slate-100 p-4 dark:border-slate-800">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Unallocated Receipts Suspense (Account 2016)</h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-slate-100 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-800/50">
                <tr>
                    <th class="px-4 py-3">Receipt #</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Phone</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($payments as $p)
                    <tr>
                        <td class="px-4 py-3 font-mono font-bold">{{ $p->trans_id ?? $p['trans_id'] }}</td>
                        <td class="px-4 py-3">{{ $p->trans_time ?? $p['trans_time'] }}</td>
                        <td class="px-4 py-3">{{ $p->phone_number ?? $p['phone_number'] }}</td>
                        <td class="px-4 py-3 text-right font-bold text-emerald-600">
                            {{ $currency }} {{ number_format($p->amount ?? $p['amount'], 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="p-6 text-center text-slate-400">No unallocated transactions.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
