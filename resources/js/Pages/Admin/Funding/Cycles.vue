<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    cycles: Array,
});

const form = useForm({});

const formatDate = (date) => new Date(date).toLocaleString('ru-RU', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
});

const profit = (cycle) => (Number(cycle.amount) * (Number(cycle.profit_percent) / 100)).toFixed(2);

const confirm = (cycleId) => {
    form.patch(route('admin.funding-cycles.confirm', cycleId), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Оборотные пакеты: подтверждение выплат" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <div>
                <h1 class="text-2xl font-semibold">Оборотные пакеты · подтверждение выплат</h1>
                <p class="text-base-content/70">Циклы в статусе «Ожидает подтверждения».</p>
            </div>

            <div class="card bg-base-100 border border-base-300">
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                        <tr>
                            <th>Трейдер</th>
                            <th>Команда</th>
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
                            <td>{{ cycle.user?.name || `#${cycle.user_id}` }}</td>
                            <td>{{ cycle.user?.team_leader?.name || '—' }}</td>
                            <td>{{ cycle.product?.name || '—' }}</td>
                            <td>{{ Number(cycle.amount).toFixed(2) }} USDT</td>
                            <td>+{{ profit(cycle) }} USDT</td>
                            <td>{{ formatDate(cycle.funded_at) }}</td>
                            <td>{{ formatDate(cycle.return_at) }}</td>
                            <td class="text-right">
                                <PrimaryButton
                                    :disabled="form.processing"
                                    @click="confirm(cycle.id)"
                                >
                                    Подтвердить выплату
                                </PrimaryButton>
                            </td>
                        </tr>
                        <tr v-if="cycles.length === 0">
                            <td colspan="8" class="text-center text-base-content/60 py-8">
                                Нет циклов, ожидающих подтверждения.
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
