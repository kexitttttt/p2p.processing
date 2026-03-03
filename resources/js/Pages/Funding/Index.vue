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
    balance: Number
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
        preserveScroll: true
    });
};

const profitCalculation = computed(() => {
    if (!form.amount || !selectedProduct.value) return 0;
    return (form.amount * (selectedProduct.value.profit_percent / 100)).toFixed(2);
});

// Форматирование даты
const formatDate = (date) => {
    return new Date(date).toLocaleString('ru-RU', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
};

// Бейджи статусов
const getStatusClasses = (status) => {
    const classes = {
        'active': 'bg-blue-500/10 text-blue-400 border-blue-500/20',
        'ready_to_close': 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20 animate-pulse',
        'completed': 'bg-green-500/10 text-green-400 border-green-500/20',
        'cancelled': 'bg-red-500/10 text-red-400 border-red-500/20',
    };
    return classes[status] || 'bg-gray-500/10 text-gray-400';
};

const getStatusLabel = (status) => {
    const labels = {
        'active': 'В работе',
        'ready_to_close': 'Ожидает подтверждения',
        'completed': 'Выплачено',
        'cancelled': 'Отменено',
    };
    return labels[status] || status;
};
</script>

<template>
    <Head title="Оборотные пакеты" />

    <AuthenticatedLayout>
        <div class="py-12 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
            
            <div class="flex flex-col md:flex-row justify-between items-end md:items-center border-b border-gray-700 pb-6">
                <div>
                    <h2 class="text-3xl font-bold text-white tracking-tight">Оборотные пакеты</h2>
                    <p class="text-gray-400 mt-1">Инвестируйте в оборотные средства</p>
                </div>
                <div class="mt-4 md:mt-0 text-right">
                    <p class="text-sm text-gray-400">Доступный баланс</p>
                    <p class="text-2xl font-mono font-bold text-green-400">{{ Number(balance).toFixed(2) }} USDT</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="product in products" :key="product.id" 
                     class="bg-gray-800 rounded-2xl border border-gray-700 p-6 relative overflow-hidden group hover:border-indigo-500 transition-all duration-300">
                    
                    <div class="absolute -top-10 -right-10 w-32 h-32 bg-indigo-600/10 rounded-full blur-3xl group-hover:bg-indigo-500/20 transition-all"></div>

                    <div class="relative z-10">
                        <div class="flex justify-between items-start mb-5">
                            <div class="p-3 bg-gray-700/50 rounded-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="px-3 py-1 bg-indigo-500/20 text-indigo-300 text-xs font-bold rounded-full border border-indigo-500/30">
                                +{{ product.profit_percent }}%
                            </div>
                        </div>

                        <h3 class="text-xl font-bold text-white mb-4">{{ product.name }}</h3>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex justify-between text-sm border-b border-gray-700 pb-2">
                                <span class="text-gray-400">Срок заморозки</span>
                                <span class="text-white font-medium">{{ product.freeze_days }} дней</span>
                            </div>
                            <div class="flex justify-between text-sm pb-1">
                                <span class="text-gray-400">Доступный объём</span>
                                <span class="text-white font-medium text-xs">
                                    {{ Number(product.current_volume).toLocaleString() }} / {{ Number(product.max_total_volume).toLocaleString() }}
                                </span>
                            </div>
                            <div class="w-full bg-gray-700 h-1.5 rounded-full overflow-hidden">
                                <div class="bg-indigo-500 h-full rounded-full transition-all duration-500" 
                                     :style="`width: ${Math.min((product.current_volume / product.max_total_volume) * 100, 100)}%`">
                                </div>
                            </div>
                        </div>

                        <PrimaryButton 
                            @click="openBuyModal(product)"
                            :disabled="Number(balance) < 10" 
                            class="w-full justify-center py-3 text-base">
                            Приобрести
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <div class="bg-gray-800 rounded-2xl border border-gray-700 overflow-hidden shadow-xl">
                <div class="px-6 py-5 border-b border-gray-700">
                    <h3 class="text-lg font-medium text-white">Мои покупки</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-gray-700/30 text-gray-400 text-xs uppercase tracking-wider">
                                <th class="px-6 py-4">Пакет</th>
                                <th class="px-6 py-4">Сумма</th>
                                <th class="px-6 py-4">Ожидаемая прибыль</th>
                                <th class="px-6 py-4">Дата возврата</th>
                                <th class="px-6 py-4">Статус</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700 text-sm">
                            <tr v-for="cycle in cycles" :key="cycle.id" class="hover:bg-gray-700/20 transition-colors">
                                <td class="px-6 py-4 text-white font-medium">{{ cycle.product.name }}</td>
                                <td class="px-6 py-4 text-gray-300 font-mono">{{ Number(cycle.amount).toFixed(2) }} USDT</td>
                                <td class="px-6 py-4 text-green-400 font-mono font-bold">
                                    +{{ (cycle.amount * (cycle.profit_percent / 100)).toFixed(2) }}
                                </td>
                                <td class="px-6 py-4 text-gray-300">
                                    {{ formatDate(cycle.return_at) }}
                                </td>
                                <td class="px-6 py-4">
                                    <span :class="['px-3 py-1 rounded-full text-xs font-medium border', getStatusClasses(cycle.status)]">
                                        {{ getStatusLabel(cycle.status) }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="cycles.length === 0">
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                    У вас пока нет активных пакетов.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="isModalOpen" @close="closeModal" maxWidth="md">
            <div class="p-6 bg-gray-800 text-white border border-gray-700 rounded-lg">
                <h3 class="text-xl font-bold mb-4">Вход: {{ selectedProduct?.name }}</h3>
                
                <form @submit.prevent="submitPurchase" class="space-y-4">
                    <div>
                        <InputLabel for="amount" value="Сумма инвестиции (USDT)" />
                        <TextInput 
                            id="amount" 
                            type="number" 
                            v-model="form.amount" 
                            class="mt-1 block w-full bg-gray-900 border-gray-600 text-white focus:border-indigo-500"
                            placeholder="Мин. 10.00"
                            step="0.01"
                        />
                        <InputError :message="form.errors.amount" class="mt-2" />
                    </div>

                    <div class="bg-gray-700/30 rounded-lg p-4 space-y-2 text-sm border border-gray-700">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Процент дохода:</span>
                            <span class="text-indigo-400 font-bold">{{ selectedProduct?.profit_percent }}%</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Срок:</span>
                            <span>{{ selectedProduct?.freeze_days }} дней</span>
                        </div>
                        <div class="border-t border-gray-600 my-2 pt-2 flex justify-between text-base">
                            <span class="text-white">Прибыль:</span>
                            <span class="text-green-400 font-bold">+{{ profitCalculation }} USDT</span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-6">
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
