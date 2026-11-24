<script setup lang="ts">
import { computed } from 'vue';
import SearchableSelect from '@/components/SearchableSelect.vue';

/* eslint-disable vue/no-mutating-props */

interface Props {
    form: any;
    bankOptions: any[];
    selectedMethod: any;
    needsBankInfo: boolean;
    isMobile: boolean;
}

const props = defineProps<Props>();

const selectedBankInfo = computed(() => {
    if (!props.form.bank_id) return null;
    return props.bankOptions.find((b) => Number(b.id) === Number(props.form.bank_id));
});
</script>

<template>
    <div class="space-y-3">
        <!-- Assigned User removed and moved to BalanceConfigSection -->
        <!-- Node Provider Selection (Visible only when needed) -->
        <div
            v-if="form.payment_method_id && (needsBankInfo || isMobile)"
            class="grid animate-in grid-cols-1 gap-4 overflow-hidden duration-300 slide-in-from-top-2"
        >
            <div class="rounded-lg border border-border/60 bg-accent/5 p-3">
                <div v-if="needsBankInfo">
                    <label
                        for="bank"
                        class="mb-1.5 block text-[10px] font-black tracking-widest text-[#0F172A] uppercase"
                    >
                        Select Target Bank Entity
                    </label>
                    <SearchableSelect
                        v-model="form.bank_id"
                        :options="bankOptions"
                        label="label"
                        value-key="id"
                        placeholder="Which Bank is this?"
                        :error="form.errors.bank_id"
                    />
                    <div
                        v-if="selectedBankInfo"
                        class="mt-2.5 flex items-center justify-between rounded-md border border-border/80 bg-background/80 px-3 py-2 text-xs shadow-xs"
                    >
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-black uppercase text-muted-foreground">CBK Code:</span>
                            <span class="font-mono text-xs font-bold text-foreground">{{ selectedBankInfo.code || 'N/A' }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-black uppercase text-indigo-600">B2B Paybill:</span>
                            <span
                                v-if="selectedBankInfo.paybill_number"
                                class="rounded border border-indigo-500/20 bg-indigo-500/10 px-2 py-0.5 font-mono text-xs font-black text-indigo-600"
                            >
                                {{ selectedBankInfo.paybill_number }}
                            </span>
                            <span v-else class="text-[10px] italic text-muted-foreground">Not Configured</span>
                        </div>
                    </div>
                    <p
                        v-else
                        class="mt-1 text-[9px] font-medium text-muted-foreground italic"
                    >
                        Link this account to a verified banking partner.
                    </p>
                </div>
                <div v-else-if="isMobile">
                    <label
                        for="bank_name"
                        class="mb-1.5 block text-[10px] font-black tracking-widest text-[#0F172A] uppercase"
                    >
                        Network Provider / Service Name
                    </label>
                    <input
                        id="bank_name"
                        v-model="form.bank_name"
                        type="text"
                        class="w-full rounded-lg border border-border bg-white px-3 py-1.5 text-sm text-foreground shadow-sm placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-accent/20 focus:outline-none"
                        placeholder="e.g., Safaricom, Airtel Money"
                    />
                    <p
                        class="mt-1 text-[9px] font-medium text-muted-foreground italic"
                    >
                        Identify the carrier or service engine for this mobile
                        channel.
                    </p>
                </div>

                <!-- Nominated Number for Paybill / Mobile Channels -->
                <div v-if="isMobile || selectedBankInfo?.paybill_number" class="mt-3 border-t border-border/40 pt-3">
                    <label
                        for="nominated_number"
                        class="mb-1.5 block text-[10px] font-black tracking-widest text-[#0F172A] uppercase"
                    >
                        Daraja API Nominated Number
                        <span class="text-[9px] font-normal lowercase text-muted-foreground">(for Pull & Webhook Registration)</span>
                    </label>
                    <input
                        id="nominated_number"
                        v-model="form.nominated_number"
                        type="text"
                        class="w-full rounded-lg border border-border bg-white px-3 py-1.5 font-mono text-sm font-bold text-foreground shadow-sm placeholder:text-muted-foreground focus:border-primary focus:ring-2 focus:ring-accent/20 focus:outline-none"
                        placeholder="e.g., 254712345678"
                    />
                    <p class="mt-1 text-[9px] font-medium text-muted-foreground italic">
                        Phone number registered on Safaricom portal as Business Admin / Operator for this Paybill. Automatically used when registering Daraja Pull URLs.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
