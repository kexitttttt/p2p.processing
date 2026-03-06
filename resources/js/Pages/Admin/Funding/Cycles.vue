<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'

const props = defineProps({
    cycles: Array,
    currentStatus: String,
})

const form = useForm({ reason: '' })

const statusTabs = [
    { key: 'active', label: 'Активные' },
    { key: 'ready_to_close', label: 'Ожидают подтверждения' },
    { key: 'completed', label: 'Завершённые' },
    { key: 'cancelled', label: 'Отменённые' },
]

const formatDate = (date) => date
    ? new Date(date).toLocaleString('ru-RU', {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
    })
    : '—'

const profit = (cycle) => Number(cycle.profit_amount ?? (Number(cycle.amount) * (Number(cycle.profit_percent) / 100))).toFixed(2)

const switchStatus = (status) => {
    router.get(route('admin.funding-cycles.index'), { status }, { preserveState: true, preserveScroll: true, replace: true })
}

const confirm = (cycleId) => {
    form.patch(route('admin.funding-cycles.confirm', cycleId), { preserveScroll: true })
}

const cancelCycle = (cycleId) => {
    form.patch(route('admin.funding-cycles.cancel', cycleId), { preserveScroll: true })
}
</script>

<template>
    <Head title="Оборотные пакеты: подтверждение выплат" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Оборотные пакеты · договоры</h1>
                    <p class="text-base-content/70">Мониторинг и ручные действия по договорам.</p>
                </div>
                <Link :href="route('admin.funding-products.index')" class="btn btn-outline btn-sm">
                    Управление позициями
                </Link>
            </div>

            <div class="tabs tabs-boxed">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.key"
                    type="button"
                    class="tab"
                    :class="{ 'tab-active': currentStatus === tab.key }"
                    @click="switchStatus(tab.key)"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div class="card bg-base-100 border border-base-300">
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Трейдер</th>
                            <th>Email</th>
                            <th>Пакет</th>
                            <th>Сумма</th>
                            <th>Прибыль</th>
                            <th>Дата покупки</th>
                            <th>Дата окончания</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="cycle in cycles" :key="cycle.id">
                            <td>#{{ cycle.user_id }}</td>
                            <td>{{ cycle.user?.name || `#${cycle.user_id}` }}</td>
                            <td>{{ cycle.user?.email || '—' }}</td>
                            <td>{{ cycle.product_name || cycle.product?.name || '—' }}</td>
                            <td>{{ Number(cycle.amount).toFixed(2) }} USDT</td>
                            <td>+{{ profit(cycle) }} USDT</td>
                            <td>{{ formatDate(cycle.funded_at) }}</td>
                            <td>{{ formatDate(cycle.return_at) }}</td>
                            <td class="text-right space-x-2">
                                <PrimaryButton
                                    v-if="currentStatus === 'ready_to_close'"
                                    :disabled="form.processing"
                                    @click="confirm(cycle.id)"
                                >
                                    Подтвердить
                                </PrimaryButton>
                                <button
                                    v-if="['active','ready_to_close'].includes(currentStatus)"
                                    class="btn btn-error btn-sm"
                                    :disabled="form.processing"
                                    @click="cancelCycle(cycle.id)"
                                >
                                    Аннулировать
                                </button>
                            </td>
                        </tr>
                        <tr v-if="cycles.length === 0">
                            <td colspan="9" class="text-center text-base-content/60 py-8">
                                По выбранному статусу договоров нет.
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
