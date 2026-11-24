<script setup lang="ts">
import {
    ClipboardCheck,
    Loader2,
    Smartphone,
    UserCircle,
    X,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    cheque: any;
    processing: boolean;
}>();

const emit = defineEmits(['close', 'confirm']);

const collectionForm = ref({
    collected_by_name: props.cheque?.collected_by_name || '',
    collected_by_mobile: props.cheque?.collected_by_mobile || '',
    sales_rep_name: props.cheque?.sales_rep_name || '',
});

watch(
    () => props.cheque,
    (newCheque) => {
        if (newCheque) {
            collectionForm.value.collected_by_name = newCheque.collected_by_name || '';
            collectionForm.value.collected_by_mobile = newCheque.collected_by_mobile || '';
            collectionForm.value.sales_rep_name = newCheque.sales_rep_name || '';
        }
    },
    { immediate: true }
);

const supplier = computed(() => {
    let s = props.cheque?.chequeable?.supplier;

    if (!s && props.cheque?.chequeable?.allocations?.length) {
        for (const alloc of props.cheque.chequeable.allocations) {
            if (alloc.grn?.supplier) {
                s = alloc.grn.supplier;
                break;
            }
            if (alloc.invoice?.supplier) {
                s = alloc.invoice.supplier;
                break;
            }
        }
    }

    return s || null;
});

const supplierName = computed(() => {
    return supplier.value?.company_name || supplier.value?.name || '';
});

const salesReps = computed(() => {
    const s = supplier.value;
    if (!s) return [];

    let reps: any[] = [];
    const rawReps = s.sales_representatives;
    if (typeof rawReps === 'string') {
        try {
            reps = JSON.parse(rawReps) || [];
        } catch {
            reps = [];
        }
    } else if (Array.isArray(rawReps)) {
        reps = [...rawReps];
    }

    const validReps = reps
        .filter((r: any) => r && r.name && r.active !== false)
        .map((r: any) => ({
            name: r.name,
            mobile_no: r.mobile_no || '',
            role: 'Sales Rep',
        }));

    // If management contact person exists and is not a placeholder, include as an option
    const contactName = (s.management_contact_person_names || s.contact_person || '').trim();
    const contactPhone = (s.management_telephone || s.telephone || '').trim();
    const isInvalidPhone =
        !contactPhone ||
        ['n/a', 'na', 'not available', 'information not available', 'none'].includes(contactPhone.toLowerCase());
    const isInvalidName =
        !contactName ||
        ['n/a', 'na', 'not available', 'information not available', 'none'].includes(contactName.toLowerCase());

    if (!isInvalidName) {
        const alreadyIncluded = validReps.some(
            (r: any) => r.name.toLowerCase() === contactName.toLowerCase()
        );
        if (!alreadyIncluded) {
            validReps.push({
                name: contactName,
                mobile_no: isInvalidPhone ? '' : contactPhone,
                role: 'Management',
            });
        }
    }

    return validReps;
});

const selectRep = (rep: any) => {
    if (
        collectionForm.value.sales_rep_name === rep.name &&
        collectionForm.value.collected_by_name === rep.name
    ) {
        collectionForm.value.collected_by_name = '';
        collectionForm.value.collected_by_mobile = '';
        collectionForm.value.sales_rep_name = '';
    } else {
        collectionForm.value.collected_by_name = rep.name || '';
        collectionForm.value.collected_by_mobile = rep.mobile_no || '';
        collectionForm.value.sales_rep_name = rep.name || '';
    }
};

const onManualNameInput = () => {
    const match = salesReps.value.find(
        (r: any) => r.name.toLowerCase() === collectionForm.value.collected_by_name.trim().toLowerCase()
    );
    if (match) {
        collectionForm.value.sales_rep_name = match.name;
        if (!collectionForm.value.collected_by_mobile && match.mobile_no) {
            collectionForm.value.collected_by_mobile = match.mobile_no;
        }
    } else {
        collectionForm.value.sales_rep_name = '';
    }
};

const isFormValid = computed(() => {
    return (
        collectionForm.value.collected_by_name.trim().length > 0 &&
        collectionForm.value.collected_by_mobile.trim().length > 0
    );
});

const handleClose = () => {
    collectionForm.value.collected_by_name = props.cheque?.collected_by_name || '';
    collectionForm.value.collected_by_mobile = props.cheque?.collected_by_mobile || '';
    collectionForm.value.sales_rep_name = props.cheque?.sales_rep_name || '';
    emit('close');
};

const handleConfirm = () => {
    if (!isFormValid.value || props.processing) return;
    emit('confirm', collectionForm.value);
};
</script>

<template>
    <div
        v-if="cheque"
        class="fixed inset-0 z-[100] flex animate-in items-center justify-center bg-black/60 p-4 duration-200 fade-in"
    >
        <div
            class="relative w-full max-w-md animate-in rounded-lg border border-border bg-card/95 p-6 shadow-2xl backdrop-blur-2xl duration-200 zoom-in-95"
            @click.stop
        >
            <!-- X Close Button -->
            <button
                type="button"
                @click="handleClose"
                class="absolute top-4 right-4 rounded-lg p-2 text-muted-foreground transition-all hover:bg-accent/10 hover:text-foreground"
                title="Close"
            >
                <X class="h-4 w-4" />
            </button>

            <div class="mb-4 flex items-center gap-4 pr-8">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-lg border border-blue-500/20 bg-blue-500/10 text-blue-600"
                >
                    <ClipboardCheck class="h-6 w-6" />
                </div>
                <div>
                    <h3
                        class="text-sm font-black tracking-widest text-foreground uppercase"
                    >
                        Cheque Collection
                    </h3>
                    <p
                        class="mt-0.5 text-[10px] font-bold tracking-tight text-muted-foreground uppercase"
                    >
                        Record Physical Handoff
                    </p>
                </div>
            </div>

            <!-- Supplier Sales Representatives quick-select -->
            <div v-if="salesReps.length > 0" class="mb-5 space-y-2 px-1">
                <div class="flex items-center justify-between">
                    <label
                        class="block text-[9px] font-black tracking-widest text-blue-600 dark:text-blue-400 uppercase"
                    >
                        Available Supplier Reps
                        <span v-if="supplierName" class="font-bold text-muted-foreground">({{ supplierName }})</span>
                    </label>
                    <span class="text-[8px] font-bold text-muted-foreground uppercase opacity-70">
                        Click to auto-fill
                    </span>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="rep in salesReps"
                        :key="rep.name"
                        type="button"
                        @click="selectRep(rep)"
                        :class="[
                            'group relative flex items-center gap-2 rounded-lg border px-3 py-2 transition-all active:scale-95 cursor-pointer',
                            collectionForm.sales_rep_name === rep.name
                                ? 'border-blue-600 bg-blue-600 text-white shadow-lg shadow-blue-500/20'
                                : 'border-blue-100 bg-blue-50/50 text-blue-900 hover:border-blue-200 hover:bg-blue-100 dark:border-blue-900/50 dark:bg-blue-950/30 dark:text-blue-200 dark:hover:bg-blue-900/50',
                        ]"
                    >
                        <div
                            :class="[
                                'flex h-6 w-6 items-center justify-center rounded-md border shadow-sm',
                                collectionForm.sales_rep_name === rep.name
                                    ? 'border-blue-400 bg-blue-500 text-white'
                                    : 'border-blue-100 bg-white text-blue-500 dark:border-blue-800 dark:bg-zinc-800 dark:text-blue-400',
                            ]"
                        >
                            <UserCircle class="h-3.5 w-3.5" />
                        </div>
                        <div class="flex flex-col items-start text-left">
                            <span
                                class="text-[9px] font-black tracking-tight uppercase"
                                :class="
                                    collectionForm.sales_rep_name === rep.name
                                        ? 'text-white'
                                        : 'text-blue-900 dark:text-blue-100'
                                "
                            >
                                {{ rep.name }}
                                <span v-if="rep.role === 'Management'" class="ml-1 text-[7.5px] font-bold opacity-75">(Contact)</span>
                            </span>
                            <span
                                v-if="rep.mobile_no"
                                class="text-[7.5px] font-bold uppercase"
                                :class="
                                    collectionForm.sales_rep_name === rep.name
                                        ? 'text-blue-100'
                                        : 'text-blue-500/80 dark:text-blue-400/80'
                                "
                            >
                                {{ rep.mobile_no }}
                            </span>
                        </div>
                    </button>
                </div>
                <div class="my-4 h-px bg-slate-100 dark:bg-border"></div>
            </div>

            <!-- Notice when outward cheque has no sales reps configured for that supplier -->
            <div
                v-else-if="supplierName && cheque?.direction === 'Outward'"
                class="mb-4 rounded-lg border border-dashed border-border/80 bg-accent/5 px-3 py-2 text-center"
            >
                <p class="text-[8.5px] font-bold text-muted-foreground uppercase">
                    No sales reps configured for {{ supplierName }}. Enter collector details manually below.
                </p>
            </div>

            <!-- Manual collector input fields -->
            <div class="mb-6 space-y-4">
                <div class="space-y-1.5">
                    <label
                        class="block px-1 text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                    >
                        Collector Name <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <UserCircle
                            class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground/40"
                        />
                        <input
                            v-model="collectionForm.collected_by_name"
                            type="text"
                            placeholder="Full Name of the collector..."
                            @input="onManualNameInput"
                            class="w-full rounded-lg border border-border bg-accent/5 py-2.5 pr-4 pl-10 text-xs font-bold transition-all outline-none focus:border-blue-500 focus:ring-blue-500/20"
                        />
                    </div>
                </div>
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between px-1">
                        <label
                            class="block text-[9px] font-black tracking-widest text-muted-foreground uppercase"
                        >
                            Mobile Number <span class="text-rose-500">*</span>
                        </label>
                        <span
                            v-if="collectionForm.collected_by_name.trim() && !collectionForm.collected_by_mobile.trim()"
                            class="text-[8px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-tight"
                        >
                            Contact required to submit
                        </span>
                    </div>
                    <div class="relative">
                        <Smartphone
                            class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground/40"
                        />
                        <input
                            v-model="collectionForm.collected_by_mobile"
                            type="tel"
                            placeholder="Collector's contact phone (e.g. 07XXXXXXXX)..."
                            :class="[
                                'w-full rounded-lg border py-2.5 pr-4 pl-10 text-xs font-bold transition-all outline-none',
                                collectionForm.collected_by_name.trim() && !collectionForm.collected_by_mobile.trim()
                                    ? 'border-amber-400 bg-amber-50/15 focus:border-amber-500 focus:ring-amber-500/20'
                                    : 'border-border bg-accent/5 focus:border-blue-500 focus:ring-blue-500/20'
                            ]"
                        />
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <button
                    type="button"
                    @click="handleClose"
                    class="px-4 py-2 text-[9px] font-black tracking-widest text-muted-foreground uppercase transition-all hover:text-foreground"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    @click="handleConfirm"
                    :disabled="!isFormValid || processing"
                    class="flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-[9px] font-black tracking-[0.1em] text-white uppercase shadow-lg shadow-blue-500/20 transition-all hover:bg-blue-500 active:scale-95 disabled:cursor-not-allowed disabled:opacity-30"
                >
                    <Loader2
                        v-if="processing"
                        class="h-3.5 w-3.5 animate-spin"
                    />
                    <ClipboardCheck v-else class="h-3.5 w-3.5" />
                    <span>{{
                        processing ? 'Saving...' : 'Record Collection'
                    }}</span>
                </button>
            </div>
        </div>
    </div>
</template>
