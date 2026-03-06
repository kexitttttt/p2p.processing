<script setup>

import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { reactive, watch } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
    products: Array,
})

/*
|--------------------------------------------------------------------------
| CREATE PRODUCT
|--------------------------------------------------------------------------
*/

const createForm = useForm({
    name: '',
    freeze_days: 1,
    profit_percent: 20,
    min_amount: 10,
    max_total_volume: 0,
    max_per_trader: 0,
    is_active: true,
})

const submitCreate = () => {

    createForm.post(route('admin.funding-products.store'), {

        preserveScroll: true,

        onSuccess: () => {

            createForm.reset()

            router.reload({
                only: ['products']
            })

        }

    })

}

/*
|--------------------------------------------------------------------------
| PRODUCT FORMS
|--------------------------------------------------------------------------
*/

const productForms = reactive({})

watch(() => props.products, () => {

    props.products.forEach(product => {

        if (!productForms[product.id]) {

            productForms[product.id] = useForm({

                name: product.name,
                freeze_days: product.freeze_days,
                profit_percent: product.profit_percent,
                min_amount: product.min_amount,
                max_total_volume: product.max_total_volume,
                max_per_trader: product.max_per_trader,
                is_active: !!product.is_active,

            })

        }

    })

}, { immediate: true })

const saveProduct = (id) => {

    productForms[id].patch(
        route('admin.funding-products.update', id),
        { preserveScroll: true }
    )

}

</script>

<template>

<Head title="Оборотные пакеты · управление позициями" />

<AuthenticatedLayout>

<div class="max-w-7xl mx-auto p-6 space-y-6">

<!-- HEADER -->

<div class="flex justify-between items-center">

<div>

<h1 class="text-2xl font-semibold">
Оборотные пакеты · управление позициями
</h1>

<p class="text-base-content/70">
Можно менять название, срок, процент и лимиты каждой позиции
</p>

</div>

<Link
:href="route('admin.funding-cycles.index')"
class="btn btn-outline btn-sm"
>
Подтверждение выплат
</Link>

</div>

<!-- CREATE PRODUCT -->

<div class="card bg-base-100 border border-base-300">

<div class="card-body">

<h2 class="card-title text-lg mb-4">
Новая позиция
</h2>

<form
class="grid grid-cols-6 gap-4 items-end"
@submit.prevent="submitCreate"
>

<div>
<label class="text-sm">Название</label>
<input
v-model="createForm.name"
class="input input-bordered w-full"
/>
</div>

<div>
<label class="text-sm">Срок (ч)</label>
<input
v-model.number="createForm.freeze_days"
type="number"
class="input input-bordered w-full"
/>
</div>

<div>
<label class="text-sm">% прибыли</label>
<input
v-model.number="createForm.profit_percent"
type="number"
class="input input-bordered w-full"
/>
</div>

<div>
<label class="text-sm">Сумма пакета</label>
<input
v-model.number="createForm.min_amount"
type="number"
class="input input-bordered w-full"
/>
</div>

<div>
<label class="text-sm">Лимит продукта</label>
<input
v-model.number="createForm.max_total_volume"
type="number"
class="input input-bordered w-full"
/>
</div>

<div>
<label class="text-sm">Лимит пакетов</label>
<input
v-model.number="createForm.max_per_trader"
type="number"
class="input input-bordered w-full"
/>
</div>

<div class="flex items-center gap-3 col-span-3">

<span class="text-sm">
Активен
</span>

<input
v-model="createForm.is_active"
type="checkbox"
class="toggle toggle-primary"
/>

</div>

<div class="flex justify-end col-span-3">

<button
class="btn btn-primary"
:disabled="createForm.processing"

>

Создать </button>

</div>

</form>

</div>

</div>

<!-- PRODUCTS TABLE -->

<div class="card bg-base-100 border border-base-300">

<div class="card-body p-0">

<table class="table w-full">

<thead class="bg-base-200 text-sm">

<tr>

<th class="pl-6">Название</th>

<th>Срок</th>

<th>%</th>

<th>Сумма пакета</th>

<th>Лимит продукта</th>

<th>Текущий объём</th>

<th>Лимит пакетов</th>

<th>Активен</th>

<th class="pr-6"></th>

</tr>

</thead>

<tbody>

<tr
v-for="product in products"
:key="product.id"
class="hover"
>

<td class="pl-6">

<input
v-if="productForms[product.id]"
v-model="productForms[product.id].name"
class="input input-bordered input-sm w-full"
/>

</td>

<td>

<input
v-if="productForms[product.id]"
v-model.number="productForms[product.id].freeze_days"
type="number"
class="input input-bordered input-sm w-full text-center"
/>

</td>

<td>

<input
v-if="productForms[product.id]"
v-model.number="productForms[product.id].profit_percent"
type="number"
class="input input-bordered input-sm w-full text-center"
/>

</td>

<td>

<input
v-if="productForms[product.id]"
v-model.number="productForms[product.id].min_amount"
type="number"
class="input input-bordered input-sm w-full"
/>

</td>

<td>

<input
v-if="productForms[product.id]"
v-model.number="productForms[product.id].max_total_volume"
type="number"
class="input input-bordered input-sm w-full"
/>

</td>

<td class="text-center">

{{ Number(product.current_volume).toFixed(2) }}

</td>

<td>

<input
v-if="productForms[product.id]"
v-model.number="productForms[product.id].max_per_trader"
type="number"
class="input input-bordered input-sm w-full"
/>

</td>

<td class="text-center">

<input
v-if="productForms[product.id]"
v-model="productForms[product.id].is_active"
type="checkbox"
class="toggle toggle-primary toggle-sm"
/>

</td>

<td class="pr-6 text-right">

<button
class="btn btn-primary btn-sm"
@click="saveProduct(product.id)"
:disabled="productForms[product.id]?.processing"

>

Сохранить </button>

</td>

</tr>

<tr v-if="products.length === 0">

<td colspan="9" class="text-center text-base-content/60 py-8">
Позиции не созданы
</td>

</tr>

</tbody>

</table>

</div>

</div>

</div>

</AuthenticatedLayout>

</template>

