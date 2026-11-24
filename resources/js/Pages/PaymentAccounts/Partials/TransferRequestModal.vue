<script setup lang="ts">
import SearchableSelect from '@/components/SearchableSelect.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatInput } from '@/lib/utils';
import { useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeftRight,
    CheckCircle2,
    Coins,
    Landmark,
    Loader2,
    RotateCcw,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    sourceAccount: any;
    allAccounts: any[];
    paymentMethods: any[];
}>();

const emit = defineEmits(['update:open']);

const selectedMethodId = ref('');
const denominationQuantities = ref<Record<string, number>>({});
const targetDenominationBalances = ref<Record<string, number>>({});
const loadingTargetDenoms = ref(false);

const availableBatches = ref<any[]>([]);
const loadingBatches = ref(false);
const selectedBatches = ref<string[]>([]);

const form = useForm({
    from_account_id: props.sourceAccount?.id || '',
    to_account_id: '',
    amount: '',
    reference: '',
    notes: '',
    denominations: null as any,
    forwarded_transfer_ids: [] as any[],
});

const sourceMethod = computed(
    () =>
        props.sourceAccount?.payment_method ||
        props.sourceAccount?.paymentMethod ||
        {},
);

const filterMethod = computed(() => {
    return props.paymentMethods.find(
        (m) => String(m.id) === String(selectedMethodId.value),
    );
});

const targetAccount = computed(() => {
    return props.allAccounts.find(
        (a) => String(a.id) === String(form.to_account_id),
    );
});

const targetMethod = computed(() => {
    if (!targetAccount.value) return null;
    return props.paymentMethods.find(
        (m) => String(m.id) === String(targetAccount.value.payment_method_id),
    );
});

const isVaultAccount = (acc: any) => {
    if (!acc) return false;
    if (
        acc.is_vault === true ||
        acc.is_vault === 1 ||
        acc.is_vault === '1' ||
        acc.is_vault === 'true'
    )
        return true;
    const name = String(acc.name || '').toLowerCase();
    return name.includes('vault');
};

const isVaultTransfer = computed(() => {
    const sourceIsVault = isVaultAccount(props.sourceAccount);
    const targetIsVault = isVaultAccount(targetAccount.value);
    return sourceIsVault || targetIsVault;
});

const isCashMethod = (method: any, account?: any) => {
    const m = method || account?.payment_method || account?.paymentMethod;
    if (!m) return false;
    return (
        Boolean(m.is_cash) ||
        m.code === 'cash' ||
        (m.name || '').toLowerCase() === 'cash'
    );
};

const isCashTransfer = computed(() => {
    const sm = sourceMethod.value;
    const tm = filterMethod.value || targetMethod.value;

    const isSourceCash = isCashMethod(sm, props.sourceAccount);
    const isTargetCash = isCashMethod(tm, targetAccount.value);

    return isSourceCash || isTargetCash;
});

const isMpesaAgent = computed(() => {
    const sm = sourceMethod.value;
    const tm = filterMethod.value || targetMethod.value;
    const isSourceAgent =
        sm?.code === 'mpesa_agent' ||
        (props.sourceAccount?.name || '').toLowerCase().includes('mpesa agent');
    const isTargetAgent =
        tm?.code === 'mpesa_agent' ||
        (targetAccount.value?.name || '').toLowerCase().includes('mpesa agent');
    return isSourceAgent || isTargetAgent;
});

const shouldShowDenominations = computed(() => {
    // Whenever sending to or receiving from a Vault account, denominations table MUST be shown!
    return isVaultTransfer.value;
});

const isAmountReadonly = computed(() => {
    // Whenever sending to or receiving from a Vault account, amount is calculated from denominations
    return isVaultTransfer.value;
});

const activeDenominationsMethod = computed(() => {
    if (filterMethod.value?.denominations?.length) return filterMethod.value;
    if (sourceMethod.value?.denominations?.length) return sourceMethod.value;
    if (targetMethod.value?.denominations?.length) return targetMethod.value;

    const foundInProps = props.paymentMethods.find(
        (m) => m.denominations && m.denominations.length > 0,
    );
    if (foundInProps?.denominations?.length) return foundInProps;

    const cashMethod = props.paymentMethods.find(
        (m) =>
            m.has_denominations ||
            m.code === 'cash' ||
            (m.name || '').toLowerCase() === 'cash',
    );
    if (cashMethod?.denominations?.length) return cashMethod;

    return {
        has_denominations: true,
        denominations: [
            { id: 'd1000', value: 1000, name: '1000.00' },
            { id: 'd500', value: 500, name: '500.00' },
            { id: 'd200', value: 200, name: '200.00' },
            { id: 'd100', value: 100, name: '100.00' },
            { id: 'd50', value: 50, name: '50.00' },
            { id: 'd40', value: 40, name: '40.00' },
            { id: 'd20', value: 20, name: '20.00' },
            { id: 'd10', value: 10, name: '10.00' },
            { id: 'd5', value: 5, name: '5.00' },
        ],
    };
});

const sortedDenominations = computed(() => {
    if (!shouldShowDenominations.value) return [];

    const method = activeDenominationsMethod.value;
    if (!method?.denominations?.length) return [];

    return [...method.denominations]
        .map((denom: any) => {
            let available = 0;
            let label = 'On Hand';

            if (props.sourceAccount?.is_vault) {
                const auditRecord =
                    props.sourceAccount.current_denominations?.find(
                        (ad: any) =>
                            String(ad.denomination_id) === String(denom.id),
                    );
                available = auditRecord ? Number(auditRecord.quantity) : 0;
                label = 'Source Stock';
            } else if (targetAccount.value?.is_vault) {
                available =
                    targetDenominationBalances.value[String(denom.id)] || 0;
                label = 'Target Stock';
            }

            return {
                ...denom,
                available_quantity: available,
                display_label: label,
            };
        })
        .sort((a: any, b: any) => Number(b.value) - Number(a.value));
});

const filteredAccounts = computed(() => {
    return props.allAccounts
        .filter((acc) => {
            const isExcluded =
                String(acc.id) === String(props.sourceAccount.id);
            if (isExcluded) return false;

            if (!selectedMethodId.value) return false;

            const methodId = String(acc.payment_method_id);
            const selectedM = filterMethod.value;
            const bankMethod = props.paymentMethods.find(
                (m) => m.name === 'Bank',
            );
            const chequeMethod = props.paymentMethods.find(
                (m) => m.name === 'Cheque',
            );

            if (selectedM?.name === 'Bank' || selectedM?.name === 'Cheque') {
                const isBank = bankMethod && methodId === String(bankMethod.id);
                const isCheque =
                    chequeMethod && methodId === String(chequeMethod.id);
                return isBank || isCheque;
            }

            return methodId === String(selectedMethodId.value);
        })
        .map((acc) => ({
            ...acc,
            displayLabel: `${acc.name} (${acc.currency})`,
        }));
});

const totalFromDenominations = computed(() => {
    if (!shouldShowDenominations.value) return 0;

    const method = activeDenominationsMethod.value;
    if (!method?.denominations?.length) return 0;

    return Object.entries(denominationQuantities.value).reduce(
        (sum, [id, qty]) => {
            const denom = method.denominations.find(
                (d: any) => String(d.id) === String(id),
            );
            const val = denom ? Number(denom.value) : 0;
            return sum + val * (Number(qty) || 0);
        },
        0,
    );
});

const formatCurrency = (amount: number | string) => {
    return Number(amount || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const processAndSetAmount = (text: string) => {
    if (isAmountReadonly.value) return;

    if (!text) {
        form.amount = '';
        return;
    }

    let inputStr = String(text);

    // 1. Check for explicit currency pattern (e.g. KES 13,000.00, Ksh13,000.00, Kes13,000.00, KSh 13,000)
    const currencyMatch = inputStr.match(
        /(?:KES|KSH|KES\.|KSH\.|USD|EUR|GBP|UGX|TZS|RWF|Ksh|Kes|KSh|\$|€|£)\s*([0-9.,]+)/i,
    );

    if (currencyMatch && currencyMatch[1]) {
        inputStr = currencyMatch[1];
    }

    // 2. Strip all non-digit and non-dot characters (removing commas, spaces, etc.)
    let cleaned = inputStr.replace(/[^0-9.]/g, '');

    // 3. Handle multiple dots if user typed extra dots
    const parts = cleaned.split('.');
    if (parts.length > 2) {
        cleaned = parts[0] + '.' + parts.slice(1).join('');
    }

    // 4. If cash transfer, cash must be whole numbers ending in 0 or 5
    if (isCashTransfer.value) {
        if (cleaned.includes('.')) {
            const numVal = Math.round(Number(cleaned));
            cleaned = isNaN(numVal) || numVal <= 0 ? '' : String(numVal);
        } else {
            cleaned = cleaned.replace(/[^0-9]/g, '');
            if (cleaned.startsWith('0') && cleaned.length > 1) {
                cleaned = cleaned.replace(/^0+/, '');
            }
        }
    } else {
        if (
            cleaned.startsWith('0') &&
            cleaned.length > 1 &&
            cleaned[1] !== '.'
        ) {
            cleaned = cleaned.replace(/^0+/, '');
        }
    }

    form.amount = cleaned;
};

const formattedAmount = computed({
    get() {
        return formatInput(form.amount);
    },
    set(val: string) {
        processAndSetAmount(val);
    },
});

const handleAmountPaste = (e: ClipboardEvent) => {
    if (isAmountReadonly.value) return;
    const text = e.clipboardData?.getData('text');
    if (text) {
        e.preventDefault();
        processAndSetAmount(text);
    }
};

const handleKeyPress = (e: KeyboardEvent) => {
    if (isAmountReadonly.value) return;
    const key = e.key;
    if (key.length > 1 || e.ctrlKey || e.metaKey) return;

    if (isCashTransfer.value) {
        if (!/^[0-9]$/.test(key)) {
            e.preventDefault();
        }
    } else {
        if (key === '.') {
            if (String(form.amount).includes('.')) {
                e.preventDefault();
            }
            return;
        }
        if (!/^[0-9]$/.test(key)) {
            e.preventDefault();
        }
    }
};

const handleAmountBlur = () => {
    if (!isCashTransfer.value) return;
    if (!form.amount) return;
    const numeric = Number(form.amount) || 0;
    if (numeric <= 0) {
        form.amount = '';
        return;
    }
    const rounded = Math.round(numeric / 5) * 5;
    form.amount = rounded > 0 ? String(rounded) : '';
};

const showConfirmModal = ref(false);

const numericAmount = computed(() => {
    if (!form.amount) return 0;
    const cleaned = String(form.amount).replace(/,/g, '').trim();
    const val = Number(cleaned);
    return isNaN(val) ? 0 : val;
});

const cashErrorMessage = computed(() => {
    if (!isCashTransfer.value) return '';
    if (!form.amount) return '';
    const amt = numericAmount.value;
    if (isNaN(amt) || amt <= 0 || amt % 5 !== 0 || amt % 1 !== 0) {
        return 'Amount must be a whole integer ending in 0 or 5.';
    }
    return '';
});

watch(totalFromDenominations, (newTotal) => {
    if (
        shouldShowDenominations.value &&
        newTotal > 0 &&
        !props.sourceAccount?.is_vault
    ) {
        form.amount = newTotal.toFixed(2);
    }
});

const totalFromBatches = computed(() => {
    return selectedBatches.value.reduce((sum, batchId) => {
        const batch = availableBatches.value.find(
            (b) => String(b.id) === String(batchId),
        );
        if (batch) {
            return (
                sum +
                (Number(batch.received_amount) || Number(batch.amount) || 0)
            );
        }
        return sum;
    }, 0);
});

const isAllBatchesSelected = computed({
    get() {
        if (!availableBatches.value.length) return false;
        return selectedBatches.value.length === availableBatches.value.length;
    },
    set(val: boolean) {
        if (val) {
            selectedBatches.value = availableBatches.value.map((b) => String(b.id));
        } else {
            selectedBatches.value = [];
        }
    },
});

const selectedBatchesDenominations = computed(() => {
    if (!props.sourceAccount?.is_vault || selectedBatches.value.length === 0)
        return [];

    const denomMap: Record<number, number> = {};
    selectedBatches.value.forEach((batchId) => {
        const batch = availableBatches.value.find(
            (b) => String(b.id) === String(batchId),
        );
        if (batch && batch.denominations) {
            batch.denominations.forEach((d: any) => {
                const val = Number(d.denomination?.value || 0);
                const qty = Number(d.received_quantity || d.quantity || 0);
                if (val > 0 && qty > 0) {
                    denomMap[val] = (denomMap[val] || 0) + qty;
                }
            });
        }
    });

    return Object.entries(denomMap)
        .map(([val, qty]) => ({
            value: Number(val),
            quantity: qty,
            total: Number(val) * qty,
        }))
        .sort((a, b) => b.value - a.value);
});

const vaultDenominationShortages = computed(() => {
    if (!props.sourceAccount?.is_vault || selectedBatches.value.length === 0)
        return [];

    const requestedMap: Record<string, { denomId: string; value: number; requestedQty: number }> = {};

    selectedBatches.value.forEach((batchId) => {
        const batch = availableBatches.value.find(
            (b) => String(b.id) === String(batchId),
        );
        if (batch && batch.denominations) {
            batch.denominations.forEach((d: any) => {
                const val = Number(d.denomination?.value || 0);
                const denomId = String(d.denomination_id || d.denomination?.id || '');
                const qty = Number(d.received_quantity || d.quantity || 0);

                if (denomId && qty > 0) {
                    if (!requestedMap[denomId]) {
                        requestedMap[denomId] = {
                            denomId,
                            value: val,
                            requestedQty: 0,
                        };
                    }
                    requestedMap[denomId].requestedQty += qty;
                }
            });
        }
    });

    const shortages: Array<{
        denomId: string;
        value: number;
        requestedQty: number;
        availableQty: number;
        shortage: number;
    }> = [];

    Object.values(requestedMap).forEach((item) => {
        const auditRecord = props.sourceAccount?.current_denominations?.find(
            (ad: any) => String(ad.denomination_id) === String(item.denomId),
        );
        const availableQty = auditRecord ? Number(auditRecord.quantity || 0) : 0;

        if (item.requestedQty > availableQty) {
            shortages.push({
                denomId: item.denomId,
                value: item.value,
                requestedQty: item.requestedQty,
                availableQty,
                shortage: item.requestedQty - availableQty,
            });
        }
    });

    return shortages.sort((a, b) => b.value - a.value);
});

const totalSelectedNotesCount = computed(() => {
    return selectedBatchesDenominations.value.reduce(
        (sum, d) => sum + d.quantity,
        0,
    );
});

watch(totalFromBatches, (newTotal) => {
    if (props.sourceAccount?.is_vault && newTotal > 0) {
        form.amount = newTotal.toFixed(2);
    } else if (props.sourceAccount?.is_vault) {
        form.amount = '';
    }
});

watch(selectedMethodId, () => {
    if (!props.sourceAccount.linked_bank_account_id) {
        form.to_account_id = '';
    }
});

watch(
    () => form.to_account_id,
    async (val) => {
        if (!val) {
            targetDenominationBalances.value = {};
            return;
        }
        if (targetAccount.value?.is_vault && !props.sourceAccount?.is_vault) {
            loadingTargetDenoms.value = true;
            try {
                const response = await fetch(
                    `/organization/purchases/accounts-payable/account-denominations?payment_account_id=${encodeURIComponent(val)}`,
                );
                if (!response.ok)
                    throw new Error('Network response was not ok');
                const data = await response.json();
                targetDenominationBalances.value = data;
            } catch (e) {
                console.error('Failed to fetch target denominations', e);
            } finally {
                loadingTargetDenoms.value = false;
            }
        }
    },
);

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            form.reset();
            form.clearErrors();
            selectedMethodId.value = '';
            denominationQuantities.value = {};
            targetDenominationBalances.value = {};
            availableBatches.value = [];
            selectedBatches.value = [];
            showConfirmModal.value = false;

            form.from_account_id = props.sourceAccount.id;

            if (props.sourceAccount?.is_vault) {
                loadingBatches.value = true;
                fetch(
                    `/organization/financial-profiles/payment-accounts/${props.sourceAccount.id}/available-batches`,
                )
                    .then((res) => res.json())
                    .then((data) => {
                        availableBatches.value = data;
                    })
                    .catch((e) => console.error(e))
                    .finally(() => {
                        loadingBatches.value = false;
                    });
            }

            if (props.sourceAccount.linked_bank_account_id) {
                const targetId = props.sourceAccount.linked_bank_account_id;
                const targetAcc = props.allAccounts.find(
                    (a) => String(a.id) === String(targetId),
                );

                if (targetAcc) {
                    form.to_account_id = String(targetId);
                    selectedMethodId.value = String(
                        targetAcc.payment_method_id,
                    );
                }
            }
        }
    },
    { immediate: true },
);

const clearDenoms = () => {
    denominationQuantities.value = {};
};

const transferTypeLabel = computed(() => {
    if (isVaultTransfer.value) return 'Vault Transfer';
    if (isCashTransfer.value) return 'Cash (Whole 0/5)';
    return 'Standard';
});

const balanceErrorMessage = computed(() => {
    const amt = numericAmount.value;
    const balance = Number(props.sourceAccount?.balance) || 0;
    if (amt > 0 && balance < amt) {
        return `Transfer amount (Ksh ${amt.toLocaleString(undefined, { minimumFractionDigits: 2 })}) exceeds available balance (Ksh ${balance.toLocaleString(undefined, { minimumFractionDigits: 2 })}) in ${props.sourceAccount?.name || 'this account'}.`;
    }
    return '';
});

const canSubmit = computed(() => {
    const amt = numericAmount.value;
    if (form.processing) return false;
    if (!form.to_account_id) return false;
    if (props.sourceAccount?.is_vault && selectedBatches.value.length === 0)
        return false;
    if (amt <= 0) return false;
    if (balanceErrorMessage.value) return false;
    if (vaultDenominationShortages.value.length > 0) return false;

    if (cashErrorMessage.value) return false;
    if (!form.notes || !form.notes.trim()) return false;
    return true;
});

const closeModal = () => {
    form.reset();
    form.clearErrors();
    selectedMethodId.value = '';
    denominationQuantities.value = {};
    targetDenominationBalances.value = {};
    availableBatches.value = [];
    selectedBatches.value = [];
    showConfirmModal.value = false;
    emit('update:open', false);
};

const submit = () => {
    form.clearErrors();

    if (balanceErrorMessage.value) {
        form.setError('amount', balanceErrorMessage.value);
        return;
    }

    if (cashErrorMessage.value) {
        form.setError('amount', cashErrorMessage.value);
        return;
    }

    if (!form.notes || !form.notes.trim()) {
        form.setError('notes', 'The description/notes field is mandatory.');
        return;
    }

    showConfirmModal.value = true;
};

const executeSubmit = () => {
    showConfirmModal.value = false;
    form.clearErrors();

    form.amount = String(numericAmount.value);

    if (props.sourceAccount?.is_vault) {
        form.forwarded_transfer_ids = selectedBatches.value;
        form.denominations = null;
    } else {
        form.forwarded_transfer_ids = [];
        if (
            shouldShowDenominations.value &&
            Object.values(denominationQuantities.value).some((q) => q > 0)
        ) {
            form.denominations = denominationQuantities.value;
        } else {
            form.denominations = null;
        }
    }

    form.post('/organization/financial-profiles/payment-account-transfers', {
        onSuccess: () => closeModal(),
    });
};
</script>

<template>
    <Dialog
        :open="open"
        @update:open="
            (val) => {
                if (!val) closeModal();
            }
        "
    >
        <DialogContent
            @pointer-down-outside="(e) => e.preventDefault()"
            @interact-outside="(e) => e.preventDefault()"
            @escape-key-down="(e) => e.preventDefault()"
            class="flex max-h-[92vh] flex-col overflow-hidden rounded-xl border border-border bg-card p-0 shadow-2xl sm:max-w-4xl md:max-w-5xl lg:max-w-6xl"
        >
            <!-- Modal Header -->
            <DialogHeader
                class="relative flex shrink-0 flex-col justify-between gap-3 border-b border-border/50 bg-muted/5 px-4 py-2.5 pr-12 sm:flex-row sm:items-center sm:pr-14"
            >
                <DialogDescription class="sr-only">
                    Transfer money between your organization accounts.
                </DialogDescription>

                <!-- Source Info -->
                <div class="flex w-full items-center gap-3 sm:w-auto">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-orange-500/20 bg-orange-500/10 text-orange-500"
                    >
                        <ArrowLeftRight class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1 text-left">
                        <DialogTitle
                            class="truncate text-sm font-black tracking-tight text-foreground uppercase"
                            :title="sourceAccount.name"
                        >
                            Transfer From:
                            <span class="text-orange-600">{{
                                sourceAccount.name
                            }}</span>
                        </DialogTitle>
                        <div
                            class="mt-0.5 flex items-center gap-1.5 text-[10px] font-bold tracking-widest text-muted-foreground uppercase"
                        >
                            <span>Balance:</span>
                            <span
                                class="font-black text-emerald-600 tabular-nums"
                                >{{ sourceAccount.currency }}
                                {{
                                    formatCurrency(sourceAccount.balance)
                                }}</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Target Info (if selected) -->
                <div
                    v-if="targetAccount"
                    class="flex w-full animate-in items-center gap-3 border-t border-border/50 pt-3 fade-in sm:w-auto sm:border-t-0 sm:border-l sm:pt-0 sm:pl-4"
                >
                    <div class="min-w-0 flex-1 text-left sm:text-right">
                        <p
                            class="text-[9px] font-black tracking-widest text-blue-600/80 uppercase"
                        >
                            Sending To
                        </p>
                        <p
                            class="mt-0.5 truncate text-sm font-black text-foreground uppercase"
                            :title="targetAccount.name"
                        >
                            {{ targetAccount.name }}
                        </p>
                    </div>
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-blue-500/20 bg-blue-500/10 text-blue-500"
                    >
                        <Landmark class="h-5 w-5" />
                    </div>
                </div>
            </DialogHeader>

            <!-- Sticky Inputs Section -->
            <div
                class="shrink-0 border-b border-border/50 bg-background px-4 py-2.5"
            >
                <!-- Payment Method, Target Account & Amount Inputs -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="space-y-1">
                        <Label
                            class="text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                        >
                            Payment Method
                        </Label>
                        <SearchableSelect
                            v-model="selectedMethodId"
                            :options="paymentMethods"
                            label="name"
                            value-key="id"
                            placeholder="CHOOSE PAYMENT METHOD"
                            :disabled="!!sourceAccount.linked_bank_account_id"
                            class="h-8 text-xs font-bold uppercase"
                        />
                    </div>

                    <div class="space-y-1">
                        <Label
                            class="text-[9px] font-black tracking-widest text-orange-600 uppercase"
                        >
                            Send To Account
                        </Label>
                        <SearchableSelect
                            v-model="form.to_account_id"
                            :options="
                                sourceAccount.linked_bank_account_id
                                    ? allAccounts
                                          .filter(
                                              (a) =>
                                                  String(a.id) ===
                                                      String(
                                                          sourceAccount.linked_bank_account_id,
                                                      ) &&
                                                  String(a.id) !==
                                                      String(sourceAccount.id),
                                          )
                                          .map((acc) => ({
                                              ...acc,
                                              displayLabel: acc.name,
                                          }))
                                    : filteredAccounts
                            "
                            label="displayLabel"
                            value-key="id"
                            :placeholder="
                                !selectedMethodId
                                    ? 'SELECT PAYMENT METHOD FIRST'
                                    : 'CHOOSE THE RECEIVING ACCOUNT'
                            "
                            :disabled="
                                !selectedMethodId ||
                                !!sourceAccount.linked_bank_account_id
                            "
                            class="h-8 text-xs font-bold uppercase"
                        />
                    </div>

                    <div class="space-y-1">
                        <Label
                            for="amount"
                            class="text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                        >
                            Amount to Send (KES)
                        </Label>
                        <Input
                            id="amount"
                            v-model="formattedAmount"
                            type="text"
                            @keypress="handleKeyPress"
                            @paste="handleAmountPaste"
                            @blur="handleAmountBlur"
                            :readonly="isAmountReadonly"
                            :placeholder="isCashTransfer ? '0' : '0.00'"
                            class="h-8 w-full rounded-md border bg-background px-3 text-xs font-black text-foreground transition-all placeholder:text-muted-foreground focus:ring-2 focus:outline-none"
                            :class="
                                isAmountReadonly
                                    ? 'cursor-not-allowed border-border opacity-80'
                                    : balanceErrorMessage ||
                                        cashErrorMessage ||
                                        form.errors.amount
                                      ? 'border-rose-500 focus:border-rose-500 focus:ring-rose-500/20'
                                      : 'border-border focus:border-blue-500 focus:ring-blue-500/20'
                            "
                        />
                        <p
                            v-if="balanceErrorMessage"
                            class="mt-1 text-[9px] leading-tight font-black text-rose-500 uppercase"
                        >
                            {{ balanceErrorMessage }}
                        </p>
                        <p
                            v-else-if="cashErrorMessage"
                            class="mt-1 text-[9px] leading-tight font-black text-rose-500 uppercase"
                        >
                            {{ cashErrorMessage }}
                        </p>
                        <p
                            v-else-if="form.errors.amount"
                            class="mt-1 text-[9px] leading-tight font-black text-rose-500 uppercase"
                        >
                            {{ form.errors.amount }}
                        </p>
                    </div>
                </div>

                <!-- Live Selected Batches Denominations Bar -->
                <div
                    v-if="
                        sourceAccount?.is_vault &&
                        selectedBatchesDenominations.length > 0
                    "
                    class="mt-3 animate-in space-y-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 fade-in"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Coins
                                class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                            />
                            <span
                                class="text-xs font-black tracking-wider text-emerald-700 uppercase dark:text-emerald-300"
                            >
                                Live Selected Batches Denominations Breakdown
                            </span>
                        </div>
                        <div
                            class="flex items-center gap-3 text-xs font-black text-emerald-800 dark:text-emerald-300"
                        >
                            <span class="rounded bg-emerald-500/20 px-2 py-0.5"
                                >{{ selectedBatches.length }} Batch(es)
                                Selected</span
                            >
                            <span>&bull;</span>
                            <span
                                >Total Notes:
                                {{ totalSelectedNotesCount }}</span
                            >
                            <span>&bull;</span>
                            <span
                                class="font-mono text-sm font-black text-emerald-600 dark:text-emerald-400"
                                >KES {{ formatCurrency(numericAmount) }}</span
                            >
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <div
                            v-for="d in selectedBatchesDenominations"
                            :key="d.value"
                            class="flex items-center gap-2 rounded-md border border-emerald-500/30 bg-background px-3 py-1.5 shadow-xs"
                        >
                            <span class="text-xs font-black text-foreground"
                                >KES {{ formatCurrency(d.value) }}</span
                            >
                            <span
                                class="rounded bg-emerald-500/20 px-2 py-0.5 font-mono text-xs font-bold text-emerald-700 dark:text-emerald-300"
                            >
                                &times; {{ d.quantity }}
                            </span>
                            <span
                                class="font-mono text-xs font-bold text-muted-foreground"
                            >
                                (= KES {{ formatCurrency(d.total) }})
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div
                class="custom-scrollbar flex-1 space-y-3 overflow-y-auto p-4 pt-3"
            >
                <!-- Global Validation Error Banner -->
                <div
                    v-if="form.hasErrors"
                    class="animate-in rounded-lg border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-700 fade-in dark:text-rose-300"
                >
                    <div class="flex items-start gap-2.5">
                        <AlertTriangle
                            class="mt-0.5 h-4 w-4 shrink-0 text-rose-500"
                        />
                        <div class="flex-1 space-y-1">
                            <p
                                class="text-[10px] font-black tracking-wider text-rose-800 uppercase dark:text-rose-200"
                            >
                                Transfer Submission Failed
                            </p>
                            <ul
                                class="list-inside list-disc space-y-0.5 text-[11px] font-bold"
                            >
                                <li
                                    v-for="(err, key) in form.errors"
                                    :key="key"
                                >
                                    {{ err }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Batch Selection for Vault Transfers -->
                <div
                    v-if="sourceAccount?.is_vault"
                    class="space-y-2 rounded-lg border border-border/50 bg-accent/5 p-3"
                >
                    <div class="flex items-center justify-between border-b border-border/40 pb-2">
                        <div class="flex items-center gap-1.5">
                            <Coins class="h-3.5 w-3.5 text-emerald-600" />
                            <span
                                class="text-[10px] font-black tracking-widest text-emerald-700 uppercase"
                            >
                                Select Received Batches
                            </span>
                        </div>

                        <!-- Select All Checkbox Control -->
                        <div v-if="availableBatches.length > 0" class="flex items-center gap-2">
                            <label
                                class="flex items-center gap-1.5 cursor-pointer rounded bg-background px-2 py-1 text-[10px] font-black text-foreground border border-border/60 hover:bg-accent/10 transition-colors shadow-2xs"
                            >
                                <input
                                    type="checkbox"
                                    v-model="isAllBatchesSelected"
                                    class="h-3.5 w-3.5 rounded border-gray-300 text-blue-600 focus:ring-blue-600 cursor-pointer"
                                />
                                <span class="uppercase tracking-wider">
                                    Select All ({{ availableBatches.length }})
                                </span>
                            </label>
                        </div>
                    </div>

                    <div v-if="loadingBatches" class="flex justify-center p-4">
                        <Loader2
                            class="h-5 w-5 animate-spin text-muted-foreground"
                        />
                    </div>
                    <div
                        v-else-if="availableBatches.length === 0"
                        class="p-4 text-center text-[9px] font-black text-muted-foreground uppercase"
                    >
                        No pending batches available to transfer.
                    </div>
                    <div
                        v-else
                        class="custom-scrollbar max-h-[200px] space-y-2 overflow-y-auto pr-1"
                    >
                        <div
                            v-for="batch in availableBatches"
                            :key="batch.id"
                            class="flex cursor-pointer items-start gap-3 rounded-md border border-border/60 bg-background p-2.5 shadow-xs transition-colors hover:border-blue-500/50"
                            @click="
                                () => {
                                    const idStr = String(batch.id);
                                    if (selectedBatches.includes(idStr)) {
                                        selectedBatches =
                                            selectedBatches.filter(
                                                (id) => id !== idStr,
                                            );
                                    } else {
                                        selectedBatches.push(idStr);
                                    }
                                }
                            "
                        >
                            <div class="mt-0.5">
                                <input
                                    type="checkbox"
                                    :checked="
                                        selectedBatches.includes(
                                            String(batch.id),
                                        )
                                    "
                                    class="pointer-events-none h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600"
                                />
                            </div>
                            <div
                                class="flex min-w-0 flex-1 flex-col gap-2 md:flex-row md:items-center"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="truncate text-[10px] font-black text-foreground uppercase"
                                        >
                                            {{
                                                batch.from_account?.name ||
                                                'Unknown Source'
                                            }}
                                        </span>
                                        <span
                                            class="truncate text-[9px] text-muted-foreground"
                                        >
                                            {{
                                                new Date(
                                                    batch.created_at,
                                                ).toLocaleString()
                                            }}
                                            &middot; Ref: {{ batch.reference }}
                                        </span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <span
                                            v-for="denom in batch.denominations"
                                            :key="denom.id"
                                            class="inline-flex items-center rounded-sm bg-accent/50 px-1 py-0 text-[8.5px] font-bold text-muted-foreground"
                                        >
                                            {{
                                                denom.denomination?.name ||
                                                denom.denomination?.value
                                            }}
                                            &times;
                                            {{
                                                denom.received_quantity > 0
                                                    ? denom.received_quantity
                                                    : denom.quantity
                                            }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="batch.notes"
                                        class="mt-1.5 truncate text-[9px] text-muted-foreground italic"
                                        :title="batch.notes"
                                    >
                                        Note: {{ batch.notes }}
                                    </p>
                                </div>
                                <div class="shrink-0 text-right md:ml-4">
                                    <span
                                        class="text-xs font-black text-emerald-600 tabular-nums"
                                    >
                                        KES
                                        {{
                                            Number(
                                                batch.received_amount ||
                                                    batch.amount,
                                            ).toLocaleString(undefined, {
                                                minimumFractionDigits: 2,
                                            })
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="form.errors.denominations"
                        class="mt-2 text-[10px] leading-tight font-black text-rose-500 uppercase"
                    >
                        {{ form.errors.denominations }}
                    </p>

                    <!-- Live Vault Denomination Shortage Warning -->
                    <div
                        v-if="vaultDenominationShortages.length > 0"
                        class="mt-3 animate-in rounded-lg border border-amber-500/40 bg-amber-500/10 p-3 fade-in"
                    >
                        <div class="flex items-start gap-2.5">
                            <AlertTriangle
                                class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400"
                            />
                            <div class="flex-1 space-y-1.5">
                                <p
                                    class="text-[10px] font-black tracking-wider text-amber-800 uppercase dark:text-amber-200"
                                >
                                    Insufficient Vault Denomination Note Stock
                                </p>
                                <p
                                    class="text-[11px] font-bold leading-relaxed text-amber-700 dark:text-amber-300"
                                >
                                    The selected batch(es) require note denominations that exceed the Vault's current physical inventory:
                                </p>
                                <div class="flex flex-wrap gap-2 pt-1">
                                    <div
                                        v-for="s in vaultDenominationShortages"
                                        :key="s.denomId"
                                        class="flex items-center gap-2 rounded-md border border-amber-500/30 bg-background px-2.5 py-1 text-[11px] font-black"
                                    >
                                        <span class="text-foreground">KES {{ formatCurrency(s.value) }}</span>
                                        <span class="text-rose-600 dark:text-rose-400">Req: {{ s.requestedQty }}</span>
                                        <span class="text-muted-foreground">/ Avail in Vault: {{ s.availableQty }}</span>
                                        <span class="rounded bg-rose-500/20 px-1.5 py-0.5 font-mono text-[9.5px] text-rose-600 dark:text-rose-400">
                                            Short {{ s.shortage }} note(s)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Denominations Breakdown (Vault Target or M-Pesa Agent) -->
                <div
                    v-if="
                        !sourceAccount?.is_vault &&
                        shouldShowDenominations &&
                        sortedDenominations.length > 0
                    "
                    class="space-y-2 rounded-lg border border-border/50 bg-accent/5 p-3"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <Coins class="h-3.5 w-3.5 text-emerald-600" />
                            <span
                                class="text-[10px] font-black tracking-widest text-emerald-700 uppercase"
                            >
                                Denominations Breakdown
                                {{
                                    isVaultTransfer
                                        ? '(Required)'
                                        : '(Optional)'
                                }}
                            </span>
                        </div>
                        <button
                            @click="clearDenoms"
                            type="button"
                            class="flex items-center gap-1 text-[9px] font-black text-rose-500 uppercase hover:underline"
                        >
                            <RotateCcw class="h-3 w-3" /> Start Over
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                        <div
                            v-for="denom in sortedDenominations"
                            :key="denom.id"
                            class="flex flex-col gap-1 rounded-md border border-border/60 bg-background p-2 shadow-xs"
                        >
                            <div
                                class="flex items-center justify-between text-[9px] font-black"
                            >
                                <span class="text-foreground"
                                    >KES {{ denom.value }}</span
                                >
                                <span class="font-mono text-emerald-600"
                                    >{{ denom.available_quantity }}
                                    {{ denom.display_label }}</span
                                >
                            </div>
                            <Input
                                v-model.number="
                                    denominationQuantities[String(denom.id)]
                                "
                                type="number"
                                min="0"
                                :max="
                                    sourceAccount?.is_vault
                                        ? denom.available_quantity
                                        : undefined
                                "
                                class="h-7 border-border bg-background text-center text-xs font-black tabular-nums"
                                :placeholder="0"
                                @input="
                                    (e: any) => {
                                        const val = Number(e.target.value);
                                        if (
                                            sourceAccount?.is_vault &&
                                            val > denom.available_quantity
                                        )
                                            denominationQuantities[
                                                String(denom.id)
                                            ] = denom.available_quantity;
                                        if (val < 0)
                                            denominationQuantities[
                                                String(denom.id)
                                            ] = 0;
                                    }
                                "
                            />
                        </div>
                    </div>

                    <p
                        v-if="form.errors.denominations"
                        class="mt-2 text-[10px] leading-tight font-black text-rose-500 uppercase"
                    >
                        {{ form.errors.denominations }}
                    </p>
                </div>

                <!-- Reason / Notes -->
                <div class="space-y-1">
                    <Label
                        for="notes"
                        class="text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                    >
                        Description / Notes <span class="text-rose-500">*</span>
                    </Label>
                    <Textarea
                        id="notes"
                        v-model="form.notes"
                        placeholder="Reason for transferring funds..."
                        class="min-h-[60px] resize-none rounded-md border bg-background p-2 text-xs font-medium text-foreground placeholder:text-muted-foreground focus:ring-2 focus:outline-none"
                        :class="
                            form.errors.notes
                                ? 'border-rose-500 focus:border-rose-500 focus:ring-rose-500/20'
                                : 'border-border focus:border-blue-500 focus:ring-blue-500/20'
                        "
                    />
                    <p
                        v-if="form.errors.notes"
                        class="mt-1 text-[9px] leading-tight font-black text-rose-500 uppercase"
                    >
                        {{ form.errors.notes }}
                    </p>
                </div>
            </div>

            <!-- Modal Footer -->
            <DialogFooter
                class="flex shrink-0 items-center justify-end gap-2 border-t border-border bg-muted/20 px-4 py-3"
            >
                <button
                    type="button"
                    @click="closeModal"
                    class="h-8 w-full rounded-md border border-border px-5 text-[10px] font-black tracking-widest text-muted-foreground uppercase transition-all hover:bg-accent/5 hover:text-foreground sm:w-auto"
                >
                    Cancel
                </button>
                <button
                    @click="submit"
                    :disabled="!canSubmit"
                    class="flex h-8 w-full items-center justify-center gap-2 rounded-md bg-blue-600 px-6 text-[10px] font-black tracking-widest text-white uppercase shadow shadow-blue-600/20 transition-all hover:bg-blue-700 active:scale-95 disabled:opacity-50 disabled:grayscale sm:w-auto"
                >
                    <Loader2
                        v-if="form.processing"
                        class="h-4 w-4 animate-spin"
                    />
                    <CheckCircle2 v-else class="h-4 w-4" />
                    Transfer Money
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Irreversible Confirmation Modal -->
    <Dialog :open="showConfirmModal" @update:open="showConfirmModal = $event">
        <DialogContent
            class="max-w-md rounded-xl border border-amber-500/30 bg-card p-6 shadow-2xl"
        >
            <DialogHeader class="space-y-2">
                <div class="flex items-center gap-3 text-amber-600">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-amber-500/20 bg-amber-500/10"
                    >
                        <AlertTriangle class="h-5 w-5" />
                    </div>
                    <div>
                        <DialogTitle
                            class="text-base font-black text-foreground uppercase"
                        >
                            Confirm Transfer
                        </DialogTitle>
                        <DialogDescription
                            class="text-[11px] font-bold text-muted-foreground"
                        >
                            Please verify the details below before proceeding.
                        </DialogDescription>
                    </div>
                </div>
            </DialogHeader>

            <div
                class="my-4 space-y-3 rounded-lg border border-border/60 bg-muted/20 p-3 text-xs"
            >
                <div
                    class="flex justify-between border-b border-border/40 pb-2 font-black"
                >
                    <span class="text-muted-foreground uppercase"
                        >Amount to Transfer:</span
                    >
                    <span class="font-mono text-sm text-emerald-600"
                        >KES {{ formatCurrency(numericAmount) }}</span
                    >
                </div>
                <div
                    class="flex justify-between border-b border-border/40 pb-2"
                >
                    <span class="font-bold text-muted-foreground uppercase"
                        >From:</span
                    >
                    <span
                        class="max-w-[200px] truncate font-black text-foreground uppercase"
                        >{{ sourceAccount?.name }}</span
                    >
                </div>
                <div
                    class="flex justify-between border-b border-border/40 pb-2"
                >
                    <span class="font-bold text-muted-foreground uppercase"
                        >Sending To:</span
                    >
                    <span
                        class="max-w-[200px] truncate font-black text-blue-600 uppercase"
                        >{{ targetAccount?.name }}</span
                    >
                </div>
                <div
                    v-if="sourceAccount?.is_vault"
                    class="flex justify-between"
                >
                    <span class="font-bold text-muted-foreground uppercase"
                        >Selected Batches:</span
                    >
                    <span class="font-black text-purple-600 uppercase"
                        >{{ selectedBatches.length }} Batch(es)</span
                    >
                </div>
                <div
                    class="mt-3 rounded border border-amber-500/30 bg-amber-500/10 p-2.5 text-[10px] leading-relaxed font-black text-amber-700 uppercase dark:text-amber-400"
                >
                    ⚠️ Notice: This action is permanent and NOT REVERSIBLE.
                    Account balances, vault stock, and General Ledger items will
                    settle immediately.
                </div>
            </div>

            <DialogFooter class="flex gap-2 sm:justify-end">
                <button
                    type="button"
                    @click="showConfirmModal = false"
                    class="h-9 rounded-md border border-border px-4 text-xs font-black text-muted-foreground uppercase transition-all hover:bg-accent/10 hover:text-foreground"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    @click="executeSubmit"
                    :disabled="form.processing"
                    class="flex h-9 items-center justify-center gap-2 rounded-md bg-blue-600 px-5 text-xs font-black text-white uppercase shadow-md transition-all hover:bg-blue-700 active:scale-95 disabled:opacity-50"
                >
                    <Loader2
                        v-if="form.processing"
                        class="h-4 w-4 animate-spin"
                    />
                    <CheckCircle2 v-else class="h-4 w-4" />
                    <span>Confirm & Transfer</span>
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
    height: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.custom-scrollbar:hover::-webkit-scrollbar-thumb {
    background: #94a3b8;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background: #334155;
}
.dark .custom-scrollbar:hover::-webkit-scrollbar-thumb {
    background: #475569;
}
</style>
