<script setup>
import { ref, computed } from 'vue'
import { useForm, Head } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Modal from '@/Components/Modals/Modal.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
    products: Array,
    activeContracts: Array,
    historyContracts: Array,
    summary: Object,
    historySummary: Object,
    balance: Number,
})

const activeTab = ref('contracts')
const isModalOpen = ref(false)
const selectedProduct = ref(null)

const form = useForm({
    product_id: null,
    quantity: 1,
})

const openBuyModal = (product) => {
    selectedProduct.value = product
    form.product_id = product.id
    form.quantity = 1
    isModalOpen.value = true
}

const closeModal = () => {
    isModalOpen.value = false
    form.reset()
}

const submitPurchase = () => {
    form.post(route('funding.purchase'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    })
}

const traderPackages = (productId) => props.activeContracts.filter((c) => c.product_id === productId).length

const canPurchase = (product) => {
    if (!product?.is_active) return false

    const minAmount = Number(product.min_amount)
    if (Number(props.balance) < minAmount) return false

    const owned = traderPackages(product.id)
    if (Number(product.max_per_trader) > 0 && owned >= Number(product.max_per_trader)) return false

    return true
}

const purchaseTotal = computed(() => {
    if (!selectedProduct.value) return '0.00'
    return (Number(selectedProduct.value.min_amount) * Number(form.quantity)).toFixed(2)
})

const profitCalculation = computed(() => {
    if (!selectedProduct.value) return '0.00'
    return (Number(purchaseTotal.value) * (Number(selectedProduct.value.profit_percent) / 100)).toFixed(2)
})

const purchaseError = computed(() => {
    if (!selectedProduct.value) return null

    if (Number(form.quantity) < 1) return 'Минимум 1 пакет'

    const total = Number(purchaseTotal.value)

    if (Number(props.balance) < total) return 'Недостаточно средств'

    const owned = traderPackages(selectedProduct.value.id)
    if (Number(selectedProduct.value.max_per_trader) > 0 && (owned + Number(form.quantity)) > Number(selectedProduct.value.max_per_trader)) {
        return 'Превышен лимит пакетов для трейдера'
    }

    if (Number(selectedProduct.value.max_total_volume) > 0 && (Number(selectedProduct.value.current_volume) + total) > Number(selectedProduct.value.max_total_volume)) {
        return 'Лимит продукта исчерпан'
    }

    return null
})

const formatDate = (date) => new Date(date).toLocaleString('ru-RU', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
})

const getStatusLabel = (status) => ({
    active: 'Активен',
    ready_to_close: 'Ожидает подтверждения',
    completed: 'Завершён',
    cancelled: 'Отменён',
}[status] || status)

const contractProfit = (contract) => Number(contract.profit_amount ?? (Number(contract.amount) * (Number(contract.profit_percent) / 100)))
const contractTotal = (contract) => Number(contract.payout_amount ?? (Number(contract.amount) + contractProfit(contract)))

const contractProgress = (contract) => {
    if (contract.status === 'ready_to_close' || contract.status === 'completed') return 100

    const fundedAt = new Date(contract.funded_at).getTime()
    const returnAt = new Date(contract.return_at).getTime()
    const total = returnAt - fundedAt

    if (total <= 0) return 0

    const elapsed = Date.now() - fundedAt
    return Math.max(0, Math.min((elapsed / total) * 100, 100))
}

const remaining = (returnAt, status) => {
    if (status !== 'active') return '—'
    const diff = new Date(returnAt).getTime() - Date.now()
    if (diff <= 0) return 'Готов к подтверждению'

    const hours = Math.floor(diff / (1000 * 60 * 60))
    return `${hours}ч`
}
</script>

<template>
<Head title="Оборотные пакеты" />

<AuthenticatedLayout>
<div class="max-w-7xl mx-auto p-6 space-y-6">

<div class="flex justify-between items-end">
<div>
<h1 class="text-2xl font-semibold">Оборотные пакеты</h1>
<p class="text-base-content/70">Полуавтомат: покупка сразу, выплата после подтверждения админом</p>
</div>

<div class="stats border border-base-300">
<div class="stat py-3 px-4">
<div class="stat-title">Доступный баланс</div>
<div class="stat-value text-2xl">{{ Number(balance).toFixed(2) }}</div>
<div class="stat-desc">USDT</div>
</div>
</div>
</div>

<div role="tablist" class="tabs tabs-boxed w-fit">
    <button role="tab" class="tab" :class="{ 'tab-active': activeTab === 'contracts' }" @click="activeTab = 'contracts'">Мои договора</button>
    <button role="tab" class="tab" :class="{ 'tab-active': activeTab === 'history' }" @click="activeTab = 'history'">История</button>
</div>

<template v-if="activeTab === 'contracts'">
<div class="stats stats-vertical lg:stats-horizontal shadow border border-base-300 w-full">
    <div class="stat"><div class="stat-title">Активно договоров</div><div class="stat-value text-2xl">{{ summary.active_count }}</div></div>
    <div class="stat"><div class="stat-title">Сумма тела</div><div class="stat-value text-2xl">{{ Number(summary.principal_total).toFixed(2) }}</div></div>
    <div class="stat"><div class="stat-title">Ожидаемая прибыль</div><div class="stat-value text-2xl text-success">{{ Number(summary.expected_profit_total).toFixed(2) }}</div></div>
    <div class="stat"><div class="stat-title">К выплате</div><div class="stat-value text-2xl">{{ Number(summary.payout_obligation_total).toFixed(2) }}</div></div>
</div>

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
<div v-for="product in products" :key="product.id" class="card bg-base-100 border border-base-300 shadow-sm">
<div class="card-body">
<div class="flex justify-between"><h2 class="card-title">{{ product.name }}</h2><span class="badge badge-success">+{{ product.profit_percent }}%</span></div>
<div class="text-sm space-y-2">
<div class="flex justify-between"><span>Срок</span><span class="font-medium">{{ product.freeze_days }} ч.</span></div>
<div class="flex justify-between"><span>Сумма пакета</span><span class="font-medium">{{ Number(product.min_amount).toLocaleString('ru-RU') }} USDT</span></div>
<div class="flex justify-between"><span>Ваши пакеты</span><span class="font-medium">{{ traderPackages(product.id) }}<span v-if="product.max_per_trader > 0"> / {{ product.max_per_trader }}</span></span></div>
</div>
<div class="card-actions mt-3"><button class="btn btn-primary w-full" :disabled="!canPurchase(product)" @click="openBuyModal(product)">Приобрести</button></div>
</div>
</div>
</div>

<div class="grid gap-4 md:grid-cols-2" v-if="activeContracts.length">
<div v-for="contract in activeContracts" :key="contract.id" class="card bg-base-100 border border-base-300">
<div class="card-body space-y-3">
<div class="flex justify-between"><h4 class="font-semibold">{{ contract.product_name || contract.product?.name }}</h4><span class="badge badge-info">{{ getStatusLabel(contract.status) }}</span></div>
<div class="text-sm grid grid-cols-2 gap-2">
<div>Тело: {{ Number(contract.amount).toFixed(2) }} USDT</div>
<div>Прибыль: +{{ contractProfit(contract).toFixed(2) }} USDT</div>
<div>Итого: {{ contractTotal(contract).toFixed(2) }} USDT</div>
<div>Осталось: {{ remaining(contract.return_at, contract.status) }}</div>
<div>Дата входа: {{ formatDate(contract.funded_at) }}</div>
<div>Дата завершения: {{ formatDate(contract.return_at) }}</div>
</div>
<div>
<div class="flex justify-between text-xs mb-1"><span>Прогресс</span><span>{{ Math.round(contractProgress(contract)) }}%</span></div>
<progress class="progress progress-accent w-full" :value="contractProgress(contract)" max="100" />
</div>
</div>
</div>
</div>
</template>

<template v-else>
<div class="stats stats-horizontal shadow border border-base-300 w-full">
    <div class="stat"><div class="stat-title">Всего завершено</div><div class="stat-value text-2xl">{{ historySummary.completed_count }}</div></div>
    <div class="stat"><div class="stat-title">Общая прибыль</div><div class="stat-value text-2xl text-success">{{ Number(historySummary.completed_profit_total).toFixed(2) }}</div></div>
</div>

<div class="card bg-base-100 border border-base-300">
<div class="overflow-x-auto">
<table class="table table-zebra">
<thead><tr><th>Пакет</th><th>Тело</th><th>Прибыль</th><th>Итого</th><th>Статус</th><th>Дата входа</th><th>Дата завершения</th></tr></thead>
<tbody>
<tr v-for="contract in historyContracts" :key="contract.id">
<td>{{ contract.product_name || contract.product?.name }}</td>
<td>{{ Number(contract.amount).toFixed(2) }} USDT</td>
<td>{{ contract.status === 'cancelled' ? '—' : `+${contractProfit(contract).toFixed(2)} USDT` }}</td>
<td>{{ Number(contractTotal(contract)).toFixed(2) }} USDT</td>
<td>{{ getStatusLabel(contract.status) }}</td>
<td>{{ formatDate(contract.funded_at) }}</td>
<td>{{ formatDate(contract.confirmed_at || contract.updated_at) }}</td>
</tr>
<tr v-if="historyContracts.length === 0"><td colspan="7" class="text-center text-base-content/60 py-8">История пока пустая</td></tr>
</tbody>
</table>
</div>
</div>
</template>

<Modal :show="isModalOpen" @close="closeModal">
<div class="p-6">
<h3 class="text-xl font-semibold mb-4">Приобрести пакет: {{ selectedProduct?.name }}</h3>
<form @submit.prevent="submitPurchase" class="space-y-6">
<div>
<InputLabel value="Количество пакетов" />
<TextInput type="number" v-model.number="form.quantity" class="mt-1 block w-full" min="1" step="1" />
<InputError :message="form.errors.quantity || purchaseError" />
</div>
<div class="rounded-xl border border-base-300 p-4 bg-base-200 space-y-3">
<div class="flex justify-between text-sm"><span>Цена одного пакета</span><span class="font-medium">{{ Number(selectedProduct?.min_amount).toLocaleString('ru-RU') }} USDT</span></div>
<div class="flex justify-between text-sm"><span>Доходность</span><span class="font-medium text-success">+{{ selectedProduct?.profit_percent }}%</span></div>
<div class="flex justify-between text-sm"><span>Срок</span><span class="font-medium">{{ selectedProduct?.freeze_days }} ч.</span></div>
<div class="divider my-1"></div>
<div class="flex justify-between text-base"><span>Сумма покупки</span><span class="font-semibold">{{ purchaseTotal }} USDT</span></div>
<div class="flex justify-between text-base"><span>Ожидаемая прибыль</span><span class="font-semibold text-success text-lg">+{{ profitCalculation }} USDT</span></div>
</div>
<div class="flex items-center justify-end gap-4 pt-4">
<button type="button" class="btn btn-outline btn-md min-w-[120px]" @click="closeModal">Отмена</button>
<button type="submit" class="btn btn-primary btn-md min-w-[200px]" :disabled="form.processing || !!purchaseError">Подтвердить покупку</button>
</div>
</form>
</div>
</Modal>

</div>
</AuthenticatedLayout>
</template>
