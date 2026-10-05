<script setup>
import { ref, onMounted } from 'vue'
const notes = ref([]), loading = ref(true), saving = ref(false), error = ref(''), errors = ref({})
const selected = ref(null), editor = ref(false), title = ref(''), content = ref(''), total = ref(0), page = ref(1), lastPage = ref(1), query = ref(''), pendingDelete = ref(null)
let requestSequence = 0
async function api(path, options = {}) {
  const response = await fetch(`/api${path}`, { ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...options.headers } })
  if (response.status === 204) return null
  const body = await response.json()
  if (!response.ok) { const e = new Error(body.message || 'Не удалось выполнить запрос'); e.fields = body.errors || {}; throw e }
  return body
}
async function load() {
  const sequence = ++requestSequence
  loading.value = true; error.value = ''
  try { const result = await api(`/notes?${new URLSearchParams({ page: page.value, per_page: 12, search: query.value })}`)
    if (sequence !== requestSequence) return
    notes.value = result.data; total.value = result.meta.total; lastPage.value = result.meta.last_page
  } catch (e) { if (sequence === requestSequence) error.value = e.message }
  finally { if (sequence === requestSequence) loading.value = false }
}
function edit(note = null) { selected.value = note; title.value = note?.title || ''; content.value = note?.content || ''; errors.value = {}; error.value = ''; editor.value = true }
async function open(note) {
  error.value = ''
  try { const result = await api(`/notes/${note.id}`); edit(result.data) } catch (e) { error.value = e.message }
}
async function save() {
  saving.value = true; errors.value = {}; error.value = ''
  try { await api(selected.value ? `/notes/${selected.value.id}` : '/notes', { method: selected.value ? 'PATCH' : 'POST', body: JSON.stringify({ title: title.value, content: content.value }) }); editor.value = false; page.value = 1; await load() }
  catch (e) { errors.value = e.fields; error.value = Object.keys(e.fields).length ? 'Проверьте поля формы.' : e.message }
  finally { saving.value = false }
}
async function remove() {
  saving.value = true; error.value = ''
  try { await api(`/notes/${pendingDelete.value.id}`, { method: 'DELETE' }); pendingDelete.value = null; if (notes.value.length === 1 && page.value > 1) page.value--; await load() }
  catch (e) { error.value = e.message }
  finally { saving.value = false }
}
function search() { page.value = 1; load() }
function changePage(delta) { page.value += delta; load() }
const date = value => new Intl.DateTimeFormat('ru', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value))
onMounted(load)
</script>

<template>
  <div class="workspace">
    <header><a class="brand" href="/" aria-label="Заметки, главная"><span class="brand-icon">✳</span> Заметки<span class="brand-dot">.</span></a><a class="docs" href="/docs/" target="_blank" rel="noopener">API документация ↗</a></header>
    <main>
      <section class="hero"><div><p class="eyebrow">МЕСТО ДЛЯ ВАШИХ МЫСЛЕЙ</p><h1>Хорошие идеи<br>начинаются с заметки.</h1><p class="subtitle">Сохраняйте важное. Возвращайтесь к идеям. Создавайте новое.</p></div><div class="hero-art" aria-hidden="true"><span class="art-line"></span><span class="art-star">✳</span><div class="paper"><span></span><span></span><span></span><i>идея!</i></div></div></section>
      <div class="toolbar"><div class="list-title"><h2>Все заметки</h2><span class="count">{{ total }}</span></div><form class="search" @submit.prevent="search"><label class="sr-only" for="search">Поиск заметок</label><input id="search" v-model="query" maxlength="200" placeholder="Найти заметку…"/><button aria-label="Найти" type="submit">⌕</button></form><button class="primary" @click="edit()">＋ Новая заметка</button></div>
      <div v-if="error" class="alert" role="alert">{{ error }}<button v-if="!editor && !pendingDelete" @click="load">Повторить</button></div>
      <div v-if="loading" class="empty" role="status">Загружаем ваши заметки…</div>
      <div v-else-if="!notes.length" class="empty"><span class="empty-icon">✎</span><h3>{{ query ? 'Ничего не нашлось' : 'Здесь начнётся ваша следующая идея' }}</h3><p>{{ query ? 'Попробуйте другой запрос.' : 'Создайте первую заметку — мысль, план или список дел.' }}</p><button v-if="!query" class="primary" @click="edit()">Создать заметку</button></div>
      <section v-else class="grid" aria-label="Список заметок"><article v-for="(note, index) in notes" :key="note.id" class="card" :class="`tone-${index % 4}`"><div class="card-top"><span class="note-label">ЗАМЕТКА / {{ String(note.id).padStart(2, '0') }}</span><button class="delete" :aria-label="`Удалить заметку ${note.title}`" @click="pendingDelete = note">×</button></div><button class="card-body" @click="open(note)"><h3>{{ note.title }}</h3><p>{{ note.content }}</p></button><footer><time :datetime="note.updated_at">{{ date(note.updated_at) }}</time><button @click="open(note)" :aria-label="`Открыть заметку ${note.title}`">↗</button></footer></article></section>
      <nav v-if="lastPage > 1" class="pagination" aria-label="Страницы"><button :disabled="page === 1 || loading" @click="changePage(-1)">← Назад</button><span>{{ page }} / {{ lastPage }}</span><button :disabled="page === lastPage || loading" @click="changePage(1)">Далее →</button></nav>
    </main><div class="bottom"><span>Маленькие записи. Большие возможности.</span><span>LARAVEL + VUE</span></div>
    <div v-if="editor" class="overlay" @click.self="!saving && (editor = false)" @keydown.esc="!saving && (editor = false)"><section class="modal" role="dialog" aria-modal="true" aria-labelledby="editor-title"><div class="modal-header"><h2 id="editor-title">{{ selected ? 'Редактировать заметку' : 'Новая заметка' }}</h2><button :disabled="saving" aria-label="Закрыть" @click="editor = false">×</button></div><form @submit.prevent="save"><label for="title">Заголовок <span>{{ title.length }}/200</span></label><input id="title" v-model="title" required maxlength="200" autofocus :aria-invalid="!!errors.title" :disabled="saving"/><p v-if="errors.title" class="field-error">{{ errors.title[0] }}</p><label for="content">Текст заметки <span>{{ content.length }}/10000</span></label><textarea id="content" v-model="content" required maxlength="10000" rows="9" :aria-invalid="!!errors.content" :disabled="saving"></textarea><p v-if="errors.content" class="field-error">{{ errors.content[0] }}</p><p v-if="error" class="field-error" role="alert">{{ error }}</p><div class="actions"><button type="button" :disabled="saving" @click="editor = false">Отмена</button><button type="submit" class="primary" :disabled="saving">{{ saving ? 'Сохраняем…' : 'Сохранить заметку' }}</button></div></form></section></div>
    <div v-if="pendingDelete" class="overlay" @click.self="!saving && (pendingDelete = null)"><section class="modal small" role="alertdialog" aria-modal="true" aria-labelledby="delete-title"><h2 id="delete-title">Удалить заметку?</h2><p>«{{ pendingDelete.title }}» будет удалена без возможности восстановления.</p><p v-if="error" class="field-error" role="alert">{{ error }}</p><div class="actions"><button :disabled="saving" @click="pendingDelete = null">Отмена</button><button class="danger" :disabled="saving" @click="remove">{{ saving ? 'Удаляем…' : 'Удалить' }}</button></div></section></div>
  </div>
</template>
