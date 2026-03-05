<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    products: Array,
});

const createForm = useForm({
    name: '',
    freeze_days: 24,
    profit_percent: 20,
    max_total_volume: 0,
    min_amount: 1000,
    max_per_trader: 0,
    is_active: true,
});

const submitCreate = () => {
    createForm.post(route('admin.funding-products.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset('name'),
    });
};

const productForms = Object.fromEntries(
    props.products.map((product) => [
        product.id,
        useForm({
            name: product.name,
            freeze_days: product.freeze_days,
            profit_percent: product.profit_percent,
            max_total_volume: product.max_total_volume,
            min_amount: product.min_amount ?? 10,
            max_per_trader: product.max_per_trader,
            is_active: !!product.is_active,
        }),
    ]),
);

const saveProduct = (productId) => {
    productForms[productId].patch(route('admin.funding-products.update', productId), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Оборотные пакеты: позиции" />

    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold">Оборотные пакеты · управление позициями</h1>
                    <p class="text-base-content/70">Можно менять название, срок в часах, размер пакета, лимит по кол-ву пакетов на трейдера и активность.</p>
                </div>
                <Link :href="route('admin.funding-cycles.index')" class="btn btn-outline btn-sm">
                    Подтверждение выплат
                </Link>
            </div>

            <div class="card bg-base-100 border border-base-300">
                <div class="card-body">
                    <h2 class="card-title text-lg">Новая позиция</h2>
                    <form class="grid gap-3 md:grid-cols-6" @submit.prevent="submitCreate">
                        <input v-model="createForm.name" type="text" class="input input-bordered md:col-span-2" placeholder="Название" required>
                        <input v-model.number="createForm.freeze_days" type="number" min="1" class="input input-bordered" placeholder="Срок, ч" required>
                        <input v-model.number="createForm.profit_percent" type="number" min="0" step="0.01" class="input input-bordered" placeholder="%" required>
                        <input v-model.number="createForm.max_total_volume" type="number" min="0" step="0.01" class="input input-bordered" placeholder="Лимит продукта (скрыт трейдеру)" required>
                        <input v-model.number="createForm.min_amount" type="number" min="1" step="0.01" class="input input-bordered" placeholder="Сумма 1 пакета" required>
                        <input v-model.number="createForm.max_per_trader" type="number" min="0" step="1" class="input input-bordered" placeholder="Лимит пакетов на трейдера" required>
                        <label class="label cursor-pointer gap-2 md:col-span-2">
                            <span class="label-text">Активен</span>
                            <input v-model="createForm.is_active" type="checkbox" class="toggle toggle-primary">
                        </label>
                        <div class="md:col-span-4 flex justify-end">
                            <PrimaryButton :disabled="createForm.processing">Создать</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card bg-base-100 border border-base-300">
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                        <tr>
                            <th>Название</th>
                            <th>Срок (часы)</th>
                            <th>%</th>
                            <th>Лимит продукта</th>
                            <th>Текущий объём</th>
                            <th>Сумма 1 пакета</th>
                            <th>Лимит пакетов</th>
                            <th>Активен</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="product in products" :key="product.id">
                            <td class="min-w-56">
                                <input v-model="productForms[product.id].name" type="text" class="input input-bordered input-sm w-full">
                            </td>
                            <td><input v-model.number="productForms[product.id].freeze_days" type="number" min="1" class="input input-bordered input-sm w-24"></td>
                            <td><input v-model.number="productForms[product.id].profit_percent" type="number" min="0" step="0.01" class="input input-bordered input-sm w-24"></td>
                            <td><input v-model.number="productForms[product.id].max_total_volume" type="number" min="0" step="0.01" class="input input-bordered input-sm w-36"></td>
                            <td>{{ Number(product.current_volume).toFixed(2) }}</td>
                            <td><input v-model.number="productForms[product.id].min_amount" type="number" min="1" step="0.01" class="input input-bordered input-sm w-36"></td>
                            <td><input v-model.number="productForms[product.id].max_per_trader" type="number" min="0" step="1" class="input input-bordered input-sm w-36"></td>
                            <td>
                                <input v-model="productForms[product.id].is_active" type="checkbox" class="toggle toggle-primary toggle-sm">
                            </td>
                            <td class="text-right">
                                <PrimaryButton :disabled="productForms[product.id].processing" @click="saveProduct(product.id)">Сохранить</PrimaryButton>
                            </td>
                        </tr>
                        <tr v-if="products.length === 0">
                            <td colspan="9" class="text-center text-base-content/60 py-8">Позиции не созданы.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
