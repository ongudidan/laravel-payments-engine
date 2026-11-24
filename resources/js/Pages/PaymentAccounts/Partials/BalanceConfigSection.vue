<script setup lang="ts">
import SearchableSelect from '@/components/SearchableSelect.vue';

/* eslint-disable vue/no-mutating-props */
interface Props {
    form: any;
    selectedMethod: any;
    isEditing?: boolean;
    userOptions?: any[];
    currentUserAssignment?: string | null;
}

const props = defineProps<Props>();

const clearDenoms = () => {
    props.form.denominations = {};
    props.form.balance = 0;
};
</script>

<template>
    <!-- eslint-disable vue/no-mutating-props -->
    <div class="space-y-3">
        <!-- Opening Denominations Section -->
        <div
            v-if="form.is_vault || selectedMethod?.code === 'mpesa_agent'"
            class="space-y-2 rounded-lg border border-border bg-accent/5 p-3"
        >
            <div class="flex items-center justify-between">
                <label
                    class="text-[10px] font-black tracking-widest text-muted-foreground uppercase"
                    >Opening Denominations</label
                >
                <button
                    v-if="!isEditing"
                    type="button"
                    @click="clearDenoms"
                    class="text-[10px] font-black text-rose-500 uppercase hover:underline"
                >
                    Reset
                </button>
                <span
                    v-else
                    class="rounded border border-amber-200/50 bg-amber-50 px-2 py-0.5 text-[9px] font-black text-amber-500"
                    >LOCKED AFTER CREATION</span
                >
            </div>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-5">
                <div
                    v-for="denom in selectedMethod.denominations"
                    :key="denom.id"
                    class="flex flex-col items-center gap-1 rounded-lg border border-border bg-card/40 p-1.5 transition-all hover:border-primary/30"
                    :class="{
                        'border-primary/50 bg-primary/5 shadow-sm ring-1 ring-primary/10':
                            form.denominations[denom.id] > 0,
                    }"
                >
                    <span
                        class="text-[11px] font-black tracking-tight text-muted-foreground uppercase"
                    >
                        {{ Math.round(denom.value) }}
                    </span>

                    <input
                        type="number"
                        v-model.number="form.denominations[denom.id]"
                        min="0"
                        :disabled="isEditing"
                        class="w-full rounded border border-border/60 bg-white px-1 py-1 text-center text-xs font-bold focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-50"
                        placeholder="0"
                    />
                </div>
            </div>
        </div>

        <div
            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
            :class="{
                'lg:grid-cols-3':
                    selectedMethod?.code === 'mpesa_agent' ||
                    selectedMethod?.code === 'cash',
            }"
        >
            <!-- Assigned User (Moved from MethodConfigSection) -->
            <div
                v-if="
                    selectedMethod?.code === 'cash' ||
                    selectedMethod?.code === 'mpesa_agent'
                "
            >
                <label
                    for="user_id"
                    class="mb-1.5 block text-sm font-medium text-muted-foreground"
                >
                    Assigned User
                    <span class="text-[10px] lowercase italic opacity-60">
                        (Optional)</span
                    >
                </label>
                <SearchableSelect
                    v-model="form.user_id"
                    :options="userOptions"
                    label="label"
                    value-key="id"
                    placeholder="Select User (Cashier)"
                    :error="form.errors.user_id"
                />
                <div
                    v-if="currentUserAssignment"
                    class="mt-1.5 animate-in rounded-md border border-red-200 bg-red-50 p-2 duration-200 fade-in slide-in-from-top-1"
                >
                    <p class="text-[10px] leading-tight font-bold text-red-700">
                        <span class="uppercase">Error:</span> This employee is
                        already assigned to the account
                        <span class="font-black underline"
                            >"{{ currentUserAssignment }}"</span
                        >. Please select another employee.
                    </p>
                </div>
            </div>

            <!-- Float Balance Field (Only for MPESA Agent) -->
            <div v-if="selectedMethod?.code === 'mpesa_agent'">
                <label
                    for="float_balance"
                    class="mb-1.5 block text-sm font-medium text-muted-foreground"
                >
                    M-Pesa Float Balance
                </label>
                <input
                    id="float_balance"
                    v-model="form.float_balance"
                    type="number"
                    step="0.01"
                    :disabled="isEditing"
                    class="w-full rounded-lg border border-border bg-card/40 px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-accent/20 focus:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:opacity-50"
                    placeholder="0.00"
                />
                <p
                    v-if="form.errors.float_balance"
                    class="mt-1 text-xs text-red-500"
                >
                    {{ form.errors.float_balance }}
                </p>
            </div>

            <!-- Total Balance Field -->
            <div>
                <label
                    for="balance"
                    class="mb-1.5 block text-sm font-medium text-muted-foreground"
                >
                    Total Opening Balance
                    {{
                        form.is_vault || selectedMethod?.code === 'mpesa_agent'
                            ? '(Calculated)'
                            : ''
                    }}
                </label>
                <input
                    id="balance"
                    v-model="form.balance"
                    type="number"
                    step="1"
                    :readonly="
                        isEditing ||
                        form.is_vault ||
                        selectedMethod?.code === 'mpesa_agent'
                    "
                    :class="[
                        'w-full rounded-lg border border-border bg-card/40 px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-accent/20 focus:outline-none',
                        isEditing ||
                        form.is_vault ||
                        selectedMethod?.code === 'mpesa_agent'
                            ? 'cursor-not-allowed bg-accent/5 font-bold opacity-70'
                            : '',
                    ]"
                    placeholder="0"
                />
                <p v-if="form.errors.balance" class="mt-1 text-xs text-red-500">
                    {{ form.errors.balance }}
                </p>
                <p
                    v-if="selectedMethod?.code === 'cash' && !form.is_vault"
                    class="mt-1 text-xs text-amber-600"
                >
                    Amount must end in 0 or 5 and have no decimals.
                </p>
            </div>
        </div>
    </div>
</template>
