<script setup lang="ts">
import SearchableSelect from '@/components/SearchableSelect.vue';
import { computed } from 'vue';

interface Props {
    form: any;
    companies: any[];
    branches: any[];
    needsBankInfo: boolean;
    isMobile: boolean;
    accountNumberLabel: string;
    filteredMethods: any[];
}

const props = defineProps<Props>();

const companyOptions = computed(() => {
    return props.companies.map((c) => ({ id: c.id, label: c.name }));
});

const branchOptions = computed(() => {
    if (!props.form.company_id) return [];
    return props.branches
        .filter((b) => b.company_id === Number(props.form.company_id))
        .map((b) => ({ id: b.id, label: b.name }));
});
</script>

<template>
    <div class="space-y-3">
        <!-- Company, Branch, & Payment Method -->
        <div class="grid grid-cols-3 gap-4">
            <div>
                <label
                    for="company_id"
                    class="mb-1.5 block text-sm font-medium text-muted-foreground"
                >
                    Company <span class="text-red-500">*</span>
                </label>
                <SearchableSelect
                    v-model="form.company_id"
                    :options="companyOptions"
                    label="label"
                    value-key="id"
                    placeholder="Select Company"
                    :error="form.errors.company_id"
                />
            </div>

            <div>
                <label
                    for="branch_id"
                    class="mb-1.5 block text-sm font-medium text-muted-foreground"
                >
                    Branch <span class="text-red-500">*</span>
                </label>
                <SearchableSelect
                    v-model="form.branch_id"
                    :options="branchOptions"
                    label="label"
                    value-key="id"
                    placeholder="Select Branch"
                    :disabled="!form.company_id"
                    :error="form.errors.branch_id"
                />
            </div>

            <div>
                <label
                    for="method"
                    class="mb-1.5 block text-sm font-medium text-muted-foreground"
                >
                    Payment Method <span class="text-red-500">*</span>
                </label>
                <SearchableSelect
                    v-model="form.payment_method_id"
                    :options="filteredMethods"
                    label="name"
                    value-key="id"
                    placeholder="Select Method"
                    :error="form.errors.payment_method_id"
                />
            </div>
        </div>

        <!-- Account Name & Account Number Field -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label
                    for="name"
                    class="mb-1.5 block text-sm font-medium text-muted-foreground"
                >
                    Account Name <span class="text-red-500">*</span>
                </label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    class="w-full rounded-lg border border-border bg-card/40 px-3 py-1.5 text-sm text-foreground backdrop-blur-xl placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-accent/20 focus:outline-none"
                    placeholder="e.g., Equity Bank Main"
                />
                <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">
                    {{ form.errors.name }}
                </p>
            </div>

            <div>
                <label
                    for="account_number"
                    class="mb-1.5 block text-sm font-medium text-muted-foreground"
                >
                    {{ accountNumberLabel }}
                    <span
                        class="text-[9px] font-bold lowercase italic opacity-60"
                    >
                        (Optional)</span
                    >
                </label>
                <input
                    id="account_number"
                    v-model="form.account_number"
                    type="text"
                    class="w-full rounded-lg border border-border bg-card/40 px-3 py-1.5 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-accent/20 focus:outline-none"
                    :placeholder="`e.g., ${isMobile ? '0712345678' : '1234567890'}`"
                />
                <p
                    v-if="form.errors.account_number"
                    class="mt-1 text-xs text-red-500"
                >
                    {{ form.errors.account_number }}
                </p>
            </div>
        </div>
    </div>
</template>
