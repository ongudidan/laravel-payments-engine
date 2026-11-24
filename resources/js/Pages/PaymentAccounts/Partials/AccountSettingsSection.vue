<script setup lang="ts">
import SearchableSelect from '@/components/SearchableSelect.vue';

interface Props {
    form: any;
    selectedMethod: any;
    selectedMethodName: string;
    bankAccountOptions: any[];
}

const props = defineProps<Props>();
</script>

<template>
    <div class="space-y-3">
        <!-- Capabilities and Active Status -->
        <div class="grid grid-cols-3 gap-4 pt-2">
            <div class="flex items-center gap-2">
                <input
                    id="active"
                    v-model="form.is_active"
                    type="checkbox"
                    class="h-4 w-4 rounded border-border bg-card/40 text-primary focus:ring-2 focus:ring-accent/20"
                />
                <label
                    for="active"
                    class="text-sm font-medium text-muted-foreground"
                >
                    Active
                </label>
            </div>

            <div class="flex items-center gap-2">
                <input
                    id="can_make_payments"
                    v-model="form.can_make_payments"
                    type="checkbox"
                    class="h-4 w-4 rounded border-border bg-card/40 text-primary focus:ring-2 focus:ring-accent/20"
                />
                <label
                    for="can_make_payments"
                    class="text-sm font-medium text-muted-foreground"
                >
                    Can Make Payments
                </label>
            </div>

            <div class="flex items-center gap-2">
                <input
                    id="can_receive_payments"
                    v-model="form.can_receive_payments"
                    type="checkbox"
                    class="h-4 w-4 rounded border-border bg-card/40 text-primary focus:ring-2 focus:ring-accent/20"
                />
                <label
                    for="can_receive_payments"
                    class="text-sm font-medium text-muted-foreground"
                >
                    Can Receive Payments
                </label>
            </div>

            <div class="flex items-center gap-2">
                <input
                    id="is_vault"
                    v-model="form.is_vault"
                    type="checkbox"
                    class="h-4 w-4 rounded border-border bg-card/40 text-primary focus:ring-2 focus:ring-accent/20"
                />
                <label
                    for="is_vault"
                    class="text-sm font-bold font-medium text-blue-600"
                >
                    Vault
                </label>
            </div>

            <div class="flex items-center gap-2">
                <input
                    id="requires_reconciliation"
                    v-model="form.requires_reconciliation"
                    type="checkbox"
                    class="h-4 w-4 rounded border-border bg-card/40 text-primary focus:ring-2 focus:ring-accent/20"
                />
                <label
                    for="requires_reconciliation"
                    class="text-sm font-bold font-medium text-orange-600"
                >
                    Requires Daily Recon.
                </label>
            </div>
        </div>

        <!-- Linked Bank Account (Paybill Liquidity) -->
        <div
            v-if="selectedMethod?.has_linked_bank"
            class="mt-3 space-y-2 border-t border-border pt-3"
        >
            <div class="mb-2 flex items-center gap-2">
                <div
                    class="rounded border border-indigo-500/20 bg-indigo-500/10 px-2 py-0.5 text-[9px] font-black tracking-widest text-indigo-600 uppercase"
                >
                    Requires Settlement Node
                </div>
            </div>

            <div
                class="animate-in rounded-lg border border-indigo-100 bg-indigo-50/30 p-4 duration-300 fade-in slide-in-from-top-2"
            >
                <label
                    class="mb-1.5 block text-[10px] font-black tracking-widest text-indigo-900 uppercase"
                >
                    Target Settlement Bank Account
                    <span class="text-red-500">*</span>
                </label>
                <SearchableSelect
                    v-model="form.linked_bank_account_id"
                    :options="bankAccountOptions"
                    label="label"
                    value-key="id"
                    placeholder="Select Target Bank Account"
                    :error="form.errors.linked_bank_account_id"
                />
                <p class="mt-1 text-[9px] font-medium text-indigo-600 italic">
                    Since this is a {{ selectedMethodName }} channel, funds will
                    automatically settle into the selected bank.
                </p>
            </div>
        </div>
    </div>
</template>
