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
    cycles: Array,
    balance: Number,
});

const isModalOpen = ref(false);
const selectedProduct = ref(null);

const form = useForm({
    product_id: null,
    amount: '',
});

const openBuyModal = (product) => {
    selectedProduct.value = product;
    form.product_id = product.id;
    form.amount = '';
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
    if (!form.amount || !selectedProduct.value) return 0;
    return (Number(form.amount) * (Number(selectedProduct.value.profit_percent) / 100)).toFixed(2);
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
    active: 'В работе',
    ready_to_close: 'Ожидает подтверждения',
    completed: 'Выплачено',
    cancelled: 'Отменено',
}[status] || status);

const progress = (product) => {
    if (Number(product.max_total_volume) <= 0) return 0;
    return Math.min((Number(product.current_volume) / Number(product.max_total_volume)) * 100, 100);
};

const remaining = (returnAt, status) => {
    if (status !== 'active') return '—';
    const diff = new Date(returnAt).getTime() - Date.now();
    if (diff <= 0) return 'Завершён';

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));

    return `${days}д ${hours}ч`;
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
                    class="card bg-base-100 border border-base-300 shadow-sm"
                >
                    <div class="card-body p-5">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="card-title text-lg">{{ product.name }}</h2>
                            <span class="badge badge-success">+{{ product.profit_percent }}%</span>
                        </div>

                        <div class="text-sm space-y-2 text-base-content/80">
                            <div class="flex justify-between">
                                <span>Срок</span>
                                <span class="font-medium text-base-content">{{ product.freeze_days }} дн.</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Лимит на трейдера</span>
                                <span class="font-medium text-base-content">
                                    {{ Number(product.max_per_trader || 0).toLocaleString('ru-RU') }}
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span>Доступный объём</span>
                                <span class="font-medium text-base-content text-right">
                                    {{ Number(product.current_volume).toLocaleString('ru-RU') }} / {{ Number(product.max_total_volume).toLocaleString('ru-RU') }}
                                </span>
                            </div>
                        </div>

                        <progress class="progress progress-primary w-full" :value="progress(product)" max="100"></progress>

                        <div class="card-actions mt-2">
                            <PrimaryButton class="w-full justify-center" :disabled="Number(balance) < 10" @click="openBuyModal(product)">
                                Приобрести
                            </PrimaryButton>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card bg-base-100 border border-base-300">
                <div class="card-body p-0">
                    <div class="px-5 pt-5 pb-3">
                        <h3 class="text-lg font-semibold">Мои покупки</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Пакет</th>
                                <th>Сумма</th>
                                <th>Статус</th>
                                <th>Осталось времени</th>
                                <th>Ожидаемая прибыль</th>
                                <th>Дата окончания</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-for="cycle in cycles" :key="cycle.id">
                                <td>{{ cycle.product?.name || '—' }}</td>
                                <td>{{ Number(cycle.amount).toFixed(2) }} USDT</td>
                                <td>
                                    <span class="badge" :class="getStatusClasses(cycle.status)">
                                        {{ getStatusLabel(cycle.status) }}
                                    </span>
                                </td>
                                <td>{{ remaining(cycle.return_at, cycle.status) }}</td>
                                <td class="text-success">+{{ (Number(cycle.amount) * (Number(cycle.profit_percent) / 100)).toFixed(2) }} USDT</td>
                                <td>{{ formatDate(cycle.return_at) }}</td>
                            </tr>
                            <tr v-if="cycles.length === 0">
                                <td colspan="6" class="text-center text-base-content/60 py-8">У вас пока нет покупок.</td>
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
                        <InputLabel for="amount" value="Сумма (USDT)" />
                        <TextInput
                            id="amount"
                            type="number"
                            v-model="form.amount"
                            class="mt-1 block w-full"
                            placeholder="Мин. 10.00"
                            step="0.01"
                        />
                        <InputError :message="form.errors.amount" class="mt-2" />
                    </div>

                    <div class="rounded-lg border border-base-300 p-4 text-sm space-y-2">
                        <div class="flex justify-between">
                            <span>Доходность</span>
                            <span class="font-semibold">{{ selectedProduct?.profit_percent }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Срок</span>
                            <span class="font-semibold">{{ selectedProduct?.freeze_days }} дн.</span>
                        </div>
                        <div class="divider my-1"></div>
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
