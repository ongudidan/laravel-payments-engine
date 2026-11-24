<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/vue3';
import { X } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import AccountSettingsSection from './Partials/AccountSettingsSection.vue';
import BalanceConfigSection from './Partials/BalanceConfigSection.vue';
import GeneralInfoSection from './Partials/GeneralInfoSection.vue';
import MethodConfigSection from './Partials/MethodConfigSection.vue';

interface Props {
    show: boolean;
    paymentAccount?: any;
    methods: {
        id: number;
        name: string;
        has_denominations?: boolean;
        has_linked_bank?: boolean;
        denominations?: any[];
        is_wallet?: boolean;
        is_supplier_recharge?: boolean;
        parent_id?: number | null;
        code?: string;
        is_mpesa_agent?: boolean;
    }[];
    banks: {
        id: number;
        name: string;
        code?: string | null;
        paybill_number?: string | null;
    }[];
    companies: { id: number; name: string }[];
    branches: { id: number; name: string; company_id: number }[];
    users: { id: number; name: string }[];
    allBankAccounts: {
        id: number;
        name: string;
        branch_id: number;
        bank?: { id: number; name: string; paybill_number?: string | null };
    }[];
    userAssignments: Record<number, string>;
}

const props = withDefaults(defineProps<Props>(), {
    paymentAccount: null,
});

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const isEditing = computed(() => !!props.paymentAccount);

const form = useForm({
    company_id: '',
    branch_id: '',
    user_id: '',
    name: '',
    payment_method_id: '',
    account_number: '',
    nominated_number: '',
    bank_id: '',
    bank_name: '',
    currency: 'KES',
    balance: 0,
    is_vault: false,
    is_active: true,
    can_make_payments: true,
    can_receive_payments: true,
    linked_bank_account_id: '' as string | number,
    requires_reconciliation: false,
    denominations: {} as Record<number, number>,
    float_balance: 0,
});

const selectedMethod = computed(() => {
    return props.methods.find((m) => m.id === Number(form.payment_method_id));
});

const selectedMethodName = computed(() => {
    return selectedMethod.value ? selectedMethod.value.name : '';
});

const totalFromDenominations = computed(() => {
    const isMpesaAgent = selectedMethod.value?.code === 'mpesa_agent';
    if (!form.is_vault && !isMpesaAgent) return 0;

    let total = 0;
    selectedMethod.value?.denominations?.forEach((denom: any) => {
        const qty = form.denominations[denom.id] || 0;
        total += Number(denom.value) * qty;
    });
    return total;
});

watch(
    [totalFromDenominations, () => form.float_balance, () => form.is_vault],
    ([newTotal, newFloat, isVault]) => {
        let total = 0;
        const isMpesaAgent = selectedMethod.value?.code === 'mpesa_agent';

        if (isVault || isMpesaAgent) {
            total += newTotal;
        }

        if (isMpesaAgent) {
            total += Number(newFloat) || 0;
        }

        if (total > 0 || isMpesaAgent || isVault) {
            form.balance = Number(total.toFixed(2));
        }
    },
);

const filteredMethods = computed(() => {
    return props.methods.filter((m: any) => {
        // 1. dont show the one that have a parent payment method
        if (m.parent_id) return false;

        // 2. dont show supplier wallet recharge
        if (m.is_supplier_recharge) return false;

        // 3. dont show the one that is wallet
        if (m.is_wallet) return false;

        return true;
    });
});

const bankOptions = computed(() => {
    return props.banks.map((b) => ({
        id: b.id,
        label: b.paybill_number ? `${b.name} (${b.paybill_number})` : b.name,
        name: b.name,
        code: b.code,
        paybill_number: b.paybill_number,
    }));
});

const needsBankInfo = computed(() => {
    const name = selectedMethodName.value.toLowerCase();
    return (
        name.includes('bank') ||
        name.includes('cheque') ||
        name.includes('transfer')
    );
});

const userOptions = computed(() => {
    return props.users
        .filter((u) => {
            const assignedAccountName = props.userAssignments[Number(u.id)];
            // Keep if not assigned to any account, or assigned to the current account being edited
            return (
                !assignedAccountName ||
                (props.paymentAccount &&
                    assignedAccountName === props.paymentAccount.name)
            );
        })
        .map((u) => ({ id: u.id, label: u.name }));
});

const isMobile = computed(() => {
    if (selectedMethod.value?.code === 'mpesa_agent') return false;
    const name = selectedMethodName.value.toLowerCase();
    return (
        name.includes('mpesa') ||
        name.includes('mobile') ||
        name.includes('paybill')
    );
});

const accountNumberLabel = computed(() => {
    if (needsBankInfo.value) return 'Bank Account Number';
    if (isMobile.value) return 'Mobile / Paybill Number';
    return 'Account Number';
});

const bankAccountOptions = computed(() => {
    // Filter to only show accounts from the SAME BRANCH as selected on the form
    // AND exclude the current account itself when editing
    const list = props.allBankAccounts || [];
    return list
        .filter((a: any) => {
            const isDifferentAccount =
                !props.paymentAccount ||
                Number(a.id) !== Number(props.paymentAccount.id);
            const isSameBranch = a.branch_id === Number(form.branch_id);
            return isDifferentAccount && isSameBranch;
        })
        .map((a: any) => {
            const bankPaybill = a.bank?.paybill_number
                ? ` (Paybill: ${a.bank.paybill_number})`
                : '';
            return {
                id: a.id,
                label: `${a.name}${bankPaybill}`,
            };
        });
});

const currentUserAssignment = computed(() => {
    if (!form.user_id) return null;
    const assignedAccountName = props.userAssignments[Number(form.user_id)];
    // Only show if it's assigned to a DIFFERENT account than the one being edited
    if (
        assignedAccountName &&
        (!props.paymentAccount ||
            assignedAccountName !== props.paymentAccount.name)
    ) {
        return assignedAccountName;
    }
    return null;
});

// Reset branch when company changes
watch(
    () => form.company_id,
    (newVal, oldVal) => {
        if (oldVal !== undefined && oldVal !== '' && newVal !== oldVal) {
            form.branch_id = '';
        }
    },
);

watch(
    () => form.payment_method_id,
    (newVal, oldVal) => {
        // Only reset if it's a manual change and NOT during initial load of editing
        if (oldVal !== undefined && oldVal !== '' && newVal !== oldVal) {
            form.denominations = {};

            // If the new method doesn't have denominations or isn't mpesa agent, clear user_id
            const newMethod = props.methods.find(
                (m) => m.id === Number(newVal),
            );
            if (
                newMethod &&
                newMethod.code !== 'cash' &&
                newMethod.code !== 'mpesa_agent'
            ) {
                form.user_id = '';
            }
        }
    },
);

watch(
    () => props.paymentAccount,
    (newItem) => {
        if (newItem) {
            form.company_id = newItem.company_id || '';
            form.branch_id = newItem.branch_id || '';
            form.user_id = newItem.user_id || '';
            form.name = newItem.name;
            form.payment_method_id = newItem.payment_method_id || '';
            form.account_number = newItem.account_number || '';
            form.nominated_number =
                newItem.nominated_number ||
                newItem.mpesa_config?.nominated_number ||
                newItem.mpesaConfig?.nominated_number ||
                '';
            form.bank_id = newItem.bank_id || '';
            form.bank_name = newItem.bank_name || '';
            form.currency = newItem.currency;
            form.balance = newItem.opening_balance;
            form.is_vault = !!newItem.is_vault;
            form.is_active = !!newItem.is_active;
            form.can_make_payments = Boolean(
                newItem.can_make_payments !== false,
            );
            form.can_receive_payments = Boolean(
                newItem.can_receive_payments !== false,
            );
            form.requires_reconciliation = !!newItem.requires_reconciliation;
            form.linked_bank_account_id = newItem.linked_bank_account_id || '';

            // Populate denominations (check both snake_case and camelCase)
            const denoms: Record<number, number> = {};
            const sourceDenoms =
                newItem.opening_denominations ||
                newItem.openingDenominations ||
                newItem.current_denominations ||
                newItem.currentDenominations;
            if (sourceDenoms) {
                sourceDenoms.forEach((ad: any) => {
                    denoms[ad.denomination_id] = Number(ad.quantity);
                });
            }
            form.denominations = denoms;
            form.float_balance = newItem.opening_float_balance || 0;
            form.defaults();
            form.clearErrors();
        } else {
            form.reset();
            form.defaults();
            form.clearErrors();
        }
    },
    { immediate: true },
);

watch(
    () => props.show,
    (newShow) => {
        if (!newShow) {
            form.reset();
            form.clearErrors();
        }
    },
);

const submit = () => {
    if (selectedMethod.value?.code === 'cash' && !form.is_vault) {
        const amount = Number(form.balance);
        if (amount % 5 !== 0 || !Number.isInteger(amount)) {
            form.setError(
                'balance',
                'Cash amounts must end in 0 or 5 and have no decimals.',
            );
            return;
        }
    }

    if (isEditing.value && props.paymentAccount) {
        form.put(
            `/organization/financial-profiles/payment-accounts/${props.paymentAccount.id}`,
            {
                preserveScroll: true,
                onSuccess: () => emit('close'),
            },
        );
    } else {
        form.post('/organization/financial-profiles/payment-accounts', {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
    }
};

const close = () => emit('close');
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/60 p-4 sm:p-6"
    >
        <div
            class="w-full max-w-4xl overflow-hidden rounded-lg border border-border bg-card/95 shadow-xl"
        >
            <!-- Modal Header -->
            <div
                class="flex items-center justify-between border-b border-border px-6 py-4"
            >
                <h2 class="text-xl font-semibold text-foreground">
                    {{
                        isEditing
                            ? 'Edit Payment Account'
                            : 'Create Payment Account'
                    }}
                </h2>
                <button
                    @click="close"
                    class="rounded-lg p-2 text-muted-foreground transition-colors hover:bg-accent/10 hover:text-foreground"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Modal Body -->
            <form
                @submit.prevent="submit"
                class="custom-scrollbar max-h-[75vh] overflow-y-auto p-4 sm:p-5"
            >
                <div class="space-y-4">
                    <GeneralInfoSection
                        :form="form"
                        :companies="companies"
                        :branches="branches"
                        :needs-bank-info="needsBankInfo"
                        :is-mobile="isMobile"
                        :account-number-label="accountNumberLabel"
                        :filtered-methods="filteredMethods"
                    />

                    <MethodConfigSection
                        :form="form"
                        :bank-options="bankOptions"
                        :selected-method="selectedMethod"
                        :needs-bank-info="needsBankInfo"
                        :is-mobile="isMobile"
                    />

                    <BalanceConfigSection
                        :form="form"
                        :selected-method="selectedMethod"
                        :is-editing="isEditing"
                        :user-options="userOptions"
                        :current-user-assignment="currentUserAssignment"
                    />

                    <AccountSettingsSection
                        :form="form"
                        :selected-method="selectedMethod"
                        :selected-method-name="selectedMethodName"
                        :bank-account-options="bankAccountOptions"
                    />
                </div>

                <!-- Modal Footer -->
                <div
                    class="mt-8 flex justify-end gap-3 border-t border-border pt-5"
                >
                    <Button
                        type="button"
                        variant="outline"
                        @click="close"
                        :disabled="form.processing"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? 'Saving...'
                                : isEditing
                                  ? 'Update'
                                  : 'Create'
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: hsla(var(--muted-foreground), 0.2);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: hsla(var(--muted-foreground), 0.3);
}
</style>
