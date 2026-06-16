# UI KIT & POLISH SPEC

Goal: make every admin page **clean, consistent, convenient, clear**. Russian UI.
Stack: Vue 3 `<script setup>`, Tailwind 4. Import via `@/...`.

## HARD RULES (do not break behavior)
1. READ the existing page first. PRESERVE every API call, endpoint path, request body and **response key names** exactly as they are (the backend shapes are verified — do not rename keys, do not invent new ones). You are upgrading PRESENTATION + FEEDBACK only, not data flow.
2. Keep all existing functionality (filters, CRUD, modals, navigation). Do not remove features.
3. Use the shared kit below instead of ad-hoc markup. Replace any native `confirm()` with `useConfirm`. Add toast feedback.
4. Only edit YOUR assigned page file. Do not touch shared components, composables, router, services, or other pages.
5. Do NOT run npm/artisan/composer. Only write the file.

## Composables
```js
import { useToast } from '@/composables/useToast';
const toast = useToast();
toast.success('Филиал сохранён');      // green
toast.error('Не удалось удалить');      // red (also use in catch blocks)
toast.info('...'); toast.warning('...');

import { useConfirm } from '@/composables/useConfirm';
const { confirm } = useConfirm();
// returns a Promise<boolean>
async function remove(item) {
  if (!(await confirm({ title: 'Удалить?', message: `Удалить «${item.name}»?`, confirmText: 'Удалить' }))) return;
  try { await api.delete(`/branches/${item.id}`); toast.success('Удалено'); load(); }
  catch (e) { toast.error('Не удалось удалить'); }
}
```

## Components (all under @/components/ui)
- `PageHeader` — props `title`, `subtitle`; slot `#actions`. Use on every page top.
- `Card` — prop `title`; slot default + `#actions`. Container for tables/forms.
- `Btn` — props `variant`(primary|secondary|danger|ghost), `size`(sm|md), `loading`, `disabled`, `type`. Use `:loading="saving"` on submit buttons.
- `Modal` — `v-model` (boolean), `title`, `size`(sm|md|lg|xl); slots default + `#footer`.
- `Badge` — prop `color`(slate|green|red|amber|indigo|blue|violet); slot text. For type/role/category chips.
- `StatusDot` — prop `status` (online|offline|error|updating|disabled|active|inactive). Renders colored dot + RU label.
- `EmptyState` — props `icon`(emoji), `title`, `hint`; slot `#action`. Show when a list is empty.
- `Spinner` — props `label`, `center`. Show while loading.
- `FormField` — props `label`, `error`, `hint`, `required`; wraps an input.
- `TextInput` — `v-model`, `type`, `placeholder`, `disabled`.
- `SelectInput` — `v-model`, `options`([{value,label}] or strings), `placeholder`.
- `Toggle` — `v-model` (boolean). For is_active switches.

## Standard list-page pattern
```
<PageHeader title="…" subtitle="…">
  <template #actions><Btn @click="openCreate">+ Добавить</Btn></template>
</PageHeader>

<!-- optional filters -->
<Card class="mb-4"> filters (SelectInput/TextInput + Найти) </Card>

<Card>
  <Spinner v-if="loading" label="Загрузка…" />
  <EmptyState v-else-if="!items.length" icon="…" title="Пока нет …" hint="…">
    <template #action><Btn @click="openCreate">+ Добавить</Btn></template>
  </EmptyState>
  <table v-else class="w-full text-sm"> … </table>
</Card>

<Modal v-model="showModal" :title="editing ? 'Редактировать' : 'Добавить'">
  <form @submit.prevent="save" class="space-y-4">
    <FormField label="Название" required :error="errors.name"><TextInput v-model="form.name" /></FormField>
    …
  </form>
  <template #footer>
    <Btn variant="secondary" @click="showModal=false">Отмена</Btn>
    <Btn :loading="saving" @click="save">Сохранить</Btn>
  </template>
</Modal>
```

## Table style
`<thead>` row: `text-left text-slate-500 border-b border-slate-100`, `<th class="py-2.5 px-3 font-medium">`.
`<tbody>` rows: `border-b border-slate-50 hover:bg-slate-50 transition`, `<td class="py-2.5 px-3">`.
Right-align the actions column. Action buttons: `Btn size="sm"` (ghost/secondary/danger) or small text buttons.

## Feedback rules
- After successful create/update: close modal, `toast.success(...)`, reload list.
- After successful delete/activate/deactivate/assign: `toast.success(...)`, reload.
- In every `catch`: `toast.error(e?.response?.data?.message || 'Произошла ошибка')`. For form validation errors (422), map `e.response.data.errors` into a per-field `errors` object shown via FormField `:error`.
- Submit buttons show `:loading` while the request is in flight; disable to prevent double submit.
- Empty lists always show `EmptyState`, never a blank card.

## Polish details
- Consistent spacing: page content already padded; use `mb-4`/`mb-5` between sections, `space-y-4` in forms.
- Color inputs: `<input type="color">` kept, but show a small swatch + hex next to it.
- Truncate long text with `truncate max-w-[...]` + `title` attr.
- Buttons with icons: prefix emoji where it aids scanning (e.g. `+ Добавить`, `▶`, `🗑`). Keep subtle.
- Make tables horizontally scrollable on small widths: wrap in `<div class="overflow-x-auto">`.
- Use `Badge` for: device_type, media type, user role (humanized RU), category, target_type.
