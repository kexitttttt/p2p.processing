<script setup>
import { ref, computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modals/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    products: Array,
    activeContracts: Array,
    historyContracts: Array,
    summary: Object,
    historySummary: Object,
    balance: Number,
});

const isModalOpen = ref(false);
const selectedProduct = ref(null);

const form = useForm({
    product_id: null,
    quantity: 1,
});

const openBuyModal = (product) => {
    selectedProduct.value = product;
    form.product_id = product.id;
    form.quantity = 1;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    form.reset();
};

const submitPurchase = () => {
    form.post(route('funding.purchase'), {
        onSuccess: () => closeModal(),
        preserveScroll: true,
    });
};

const profitCalculation = computed(() => {
    if (!selectedProduct.value) return 0;
    return (Number(selectedProduct.value.min_amount || 10) * Number(form.quantity || 1) * (Number(selectedProduct.value.profit_percent) / 100)).toFixed(2);
});

const purchaseTotal = computed(() => {
    if (!selectedProduct.value) return 0;

    return (Number(selectedProduct.value.min_amount || 10) * Number(form.quantity || 1)).toFixed(2);
});

const formatDate = (date) => new Date(date).toLocaleString('ru-RU', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
});

const getStatusClasses = (status) => ({
    active: 'badge-info',
    ready_to_close: 'badge-warning',
    completed: 'badge-success',
    cancelled: 'badge-error',
}[status] || 'badge-ghost');

const getStatusLabel = (status) => ({
    active: 'Активен',
    ready_to_close: 'Ожидает подтверждения',
    completed: 'Завершён',
    cancelled: 'Отменён',
}[status] || status);

const availableVolume = (product) => {
    if (Number(product.max_total_volume) <= 0) return null;

    return Math.max(Number(product.max_total_volume) - Number(product.current_volume), 0);
};

const progress = (product) => {
    if (Number(product.max_total_volume) <= 0) return 0;
    return Math.min((Number(product.current_volume) / Number(product.max_total_volume)) * 100, 100);
};

const canPurchase = (product) => {
    if (!product?.is_active) return false;
    const minAmount = Number(product.min_amount || 10);

    if (Number(props.balance) < minAmount) return false;

    return true;
};

const contractProfit = (contract) => Number(contract.amount) * (Number(contract.profit_percent) / 100);
const contractTotal = (contract) => Number(contract.amount) + contractProfit(contract);

const contractProgress = (contract) => {
    if (contract.status === 'ready_to_close' || contract.status === 'completed') return 100;

    const fundedAt = new Date(contract.funded_at).getTime();
    const returnAt = new Date(contract.return_at).getTime();
    const totalDuration = returnAt - fundedAt;

    if (totalDuration <= 0) return 0;

    const elapsed = Date.now() - fundedAt;

    return Math.max(0, Math.min((elapsed / totalDuration) * 100, 100));
};

const remaining = (returnAt, status) => {
    if (status === 'ready_to_close') return 'Ожидает подтверждения';
    if (status !== 'active') return '—';
    const diff = new Date(returnAt).getTime() - Date.now();
    if (diff <= 0) return 'Завершён';

    const hours = Math.floor(diff / (1000 * 60 * 60));

    return `${hours}ч`;
};
</script>

<template>
    <Head title="Оборотные пакеты" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">Оборотные пакеты</h1>
                    <p class="text-base-content/70">Полуавтомат: покупка сразу, выплата после подтверждения админом.</p>
                </div>
                <div class="stats border border-base-300 w-full sm:w-auto">
                    <div class="stat py-3 px-4">
                        <div class="stat-title">Доступный баланс</div>
                        <div class="stat-value text-2xl">{{ Number(balance).toFixed(2) }}</div>
                        <div class="stat-desc">USDT</div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="product in products"
                    :key="product.id"
                    class="card bg-base-100 border border-base-300 shadow-sm h-full"
                >
                    <div class="card-body p-5 flex flex-col">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="card-title text-lg">{{ product.name }}</h2>
                            <span class="badge badge-success">+{{ product.profit_percent }}%</span>
                        </div>

                        <div class="text-sm space-y-2 text-base-content/80 flex-1">
                            <div class="flex justify-between">
                                <span>Срок</span>
                                <span class="font-medium text-base-content">{{ Number(product.freeze_days) }} ч.</span>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span>Сумма 1 пакета</span>
                                <span class="font-medium text-base-content text-right">
                                    {{ Number(product.min_amount || 10).toLocaleString('ru-RU') }} USDT
                                </span>
                            </div>
                        </div>

                        <progress class="progress progress-primary w-full" :value="progress(product)" max="100"></progress>

                        <div class="card-actions mt-2">
                            <PrimaryButton class="w-full justify-center" :disabled="!canPurchase(product)" @click="openBuyModal(product)">
                                Приобрести
                            </PrimaryButton>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 border border-base-300">
                <div class="card-body">
                    <h3 class="text-lg font-semibold">Мои договора · Сводка</h3>
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                        <div class="rounded-lg border border-base-300 p-3">
                            <div class="text-base-content/70">Активно договоров</div>
                            <div class="text-xl font-semibold">{{ summary.active_count }}</div>
                        </div>
                        <div class="rounded-lg border border-base-300 p-3">
                            <div class="text-base-content/70">Общая сумма тела</div>
                            <div class="text-xl font-semibold">{{ Number(summary.principal_total).toLocaleString('ru-RU') }}</div>
                        </div>
                        <div class="rounded-lg border border-base-300 p-3">
                            <div class="text-base-content/70">Ожидаемая прибыль</div>
                            <div class="text-xl font-semibold text-success">+{{ Number(summary.expected_profit_total).toLocaleString('ru-RU') }}</div>
                        </div>
                        <div class="rounded-lg border border-base-300 p-3">
                            <div class="text-base-content/70">Обязательство к выплате</div>
                            <div class="text-xl font-semibold">{{ Number(summary.payout_obligation_total).toLocaleString('ru-RU') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2" v-if="activeContracts.length">
                <div v-for="contract in activeContracts" :key="contract.id" class="card bg-base-100 border border-base-300">
                    <div class="card-body space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h4 class="text-lg font-semibold">Пакет: {{ contract.product?.name || '—' }}</h4>
                                <div class="text-sm text-base-content/70">Сумма: {{ Number(contract.amount).toLocaleString('ru-RU') }} USDT</div>
                                <div class="text-sm text-success">Доходность: +{{ Number(contract.profit_percent).toFixed(2) }}%</div>
                            </div>
                            <span class="badge" :class="getStatusClasses(contract.status)">{{ getStatusLabel(contract.status) }}</span>
                        </div>

                        <div class="text-sm grid grid-cols-2 gap-2">
                            <div>Дата входа: {{ formatDate(contract.funded_at) }}</div>
                            <div>Дата завершения: {{ formatDate(contract.return_at) }}</div>
                            <div>Прибыль: +{{ contractProfit(contract).toFixed(2) }} USDT</div>
                            <div>Итого: {{ contractTotal(contract).toFixed(2) }} USDT</div>
                        </div>

                        <div>
                            <div class="flex justify-between text-xs mb-1">
                                <span>Прогресс</span>
                                <span>{{ Math.round(contractProgress(contract)) }}%</span>
                            </div>
                            <progress class="progress progress-accent w-full" :value="contractProgress(contract)" max="100"></progress>
                            <div class="text-xs text-base-content/70 mt-1">Осталось: {{ remaining(contract.return_at, contract.status) }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card bg-base-100 border border-base-300" v-else>
                <div class="card-body text-base-content/60">Активных договоров пока нет.</div>
            </div>

            <div class="card bg-base-100 border border-base-300">
                <div class="card-body space-y-4">
                    <h3 class="text-lg font-semibold">История</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg border border-base-300 p-3">
                            <div class="text-base-content/70">Всего завершено</div>
                            <div class="text-xl font-semibold">{{ historySummary.completed_count }}</div>
                        </div>
                        <div class="rounded-lg border border-base-300 p-3">
                            <div class="text-base-content/70">Общая прибыль</div>
                            <div class="text-xl font-semibold text-success">+{{ Number(historySummary.completed_profit_total).toLocaleString('ru-RU') }}</div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="table table-sm sm:table-md">
                            <thead>
                            <tr>
                                <th>Пакет</th>
                                <th>Сумма</th>
                                <th>Прибыль</th>
                                <th>Статус</th>
                                <th>Дата завершения</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="contract in historyContracts" :key="contract.id">
                                <td>{{ contract.product?.name || '—' }}</td>
                                <td>{{ Number(contract.amount).toFixed(2) }} USDT</td>
                                <td class="text-success">+{{ contractProfit(contract).toFixed(2) }} USDT</td>
                                <td><span class="badge" :class="getStatusClasses(contract.status)">{{ getStatusLabel(contract.status) }}</span></td>
                                <td>{{ formatDate(contract.return_at) }}</td>
                            </tr>
                            <tr v-if="historyContracts.length === 0">
                                <td colspan="5" class="text-center text-base-content/60 py-8">История пока пуста.</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="isModalOpen" @close="closeModal" maxWidth="md">
            <div class="p-6">
                <h3 class="text-xl font-semibold mb-4">Приобрести пакет: {{ selectedProduct?.name }}</h3>

                <form @submit.prevent="submitPurchase" class="space-y-4">
                    <div>
                        <InputLabel for="quantity" value="Количество пакетов" />
                        <TextInput
                            id="quantity"
                            type="number"
                            v-model="form.quantity"
                            class="mt-1 block w-full"
                            placeholder="1"
                            step="1"
                            min="1"
                        />
                        <InputError :message="form.errors.quantity" class="mt-2" />
                    </div>

                    <div class="rounded-lg border border-base-300 p-4 text-sm space-y-2">
                        <div class="flex justify-between">
                            <span>Доходность</span>
                            <span class="font-semibold">{{ selectedProduct?.profit_percent }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Срок</span>
                            <span class="font-semibold">{{ selectedProduct?.freeze_days }} ч.</span>
                        </div>
                        <div class="divider my-1"></div>
                        <div class="flex justify-between">
                            <span>Сумма</span>
                            <span class="font-semibold">{{ purchaseTotal }} USDT</span>
                        </div>
                        <div class="flex justify-between text-base">
                            <span>Ожидаемая прибыль</span>
                            <span class="font-semibold text-success">+{{ profitCalculation }} USDT</span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <SecondaryButton @click="closeModal">Отмена</SecondaryButton>
                        <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            Подтвердить
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
