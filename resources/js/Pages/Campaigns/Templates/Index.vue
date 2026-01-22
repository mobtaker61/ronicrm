<template>
    <AppLayout>
        <template #header>
            Campaign Templates
        </template>

        <div class="space-y-6">
            <!-- Success Message -->
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                {{ $page.props.flash.success }}
            </div>

            <!-- Header -->
            <div class="flex justify-between items-center">
                <h2 class="text-2xl font-bold text-gray-900">Campaign Templates</h2>
                <button
                    @click="showAddModal = true"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    Create Template
                </button>
            </div>

            <!-- Templates List -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preview</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="template in templates" :key="template.id">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ template.name }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    class="px-2 py-1 text-xs font-medium rounded-full"
                                    :class="{
                                        'bg-green-100 text-green-800': template.type === 'whatsapp',
                                        'bg-blue-100 text-blue-800': template.type === 'email',
                                    }"
                                >
                                    {{ template.type }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ template.subject || '-' }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <div class="max-w-xs truncate" v-html="template.content"></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-4">
                                    <button
                                        @click="editTemplate(template)"
                                        class="text-blue-600 hover:text-blue-900"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        @click="deleteTemplate(template)"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="templates.length === 0">
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                No templates found. Create your first template!
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div
            v-if="showAddModal || editingTemplate"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
            @click.self="closeModal"
        >
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        {{ editingTemplate ? 'Edit' : 'Create' }} Template
                    </h3>

                    <form @submit.prevent="saveTemplate" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Type *</label>
                                <select
                                    v-model="form.type"
                                    required
                                    @change="handleTypeChange"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">Select Type</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="email">Email</option>
                                </select>
                            </div>
                        </div>

                        <div v-if="form.type === 'email'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Subject</label>
                            <input
                                v-model="form.subject"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>

                        <!-- Image Upload (for WhatsApp) -->
                        <div v-if="form.type === 'whatsapp'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Image (Optional)</label>
                            <div class="flex items-center space-x-4">
                                <div v-if="imagePreview || (editingTemplate && editingTemplate.image)" class="flex-shrink-0">
                                    <img
                                        :src="imagePreview || `/storage/${editingTemplate.image}`"
                                        alt="Preview"
                                        class="w-32 h-32 object-cover rounded-lg border border-gray-300"
                                    />
                                </div>
                                <div class="flex-1">
                                    <input
                                        type="file"
                                        accept="image/*"
                                        @change="handleImageChange"
                                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                                    />
                                    <p class="mt-1 text-xs text-gray-500">Upload image for WhatsApp (optional)</p>
                                </div>
                            </div>
                        </div>

                        <!-- Content Editor -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Content *</label>
                            
                            <!-- HTML Editor for Email -->
                            <div v-if="form.type === 'email'" class="space-y-2">
                                <div class="flex space-x-2 mb-2">
                                    <button
                                        type="button"
                                        @click="editorMode = 'html'"
                                        :class="[
                                            'px-3 py-1 text-sm rounded-md',
                                            editorMode === 'html' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'
                                        ]"
                                    >
                                        HTML
                                    </button>
                                    <button
                                        type="button"
                                        @click="editorMode = 'preview'"
                                        :class="[
                                            'px-3 py-1 text-sm rounded-md',
                                            editorMode === 'preview' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'
                                        ]"
                                    >
                                        Preview
                                    </button>
                                </div>
                                
                                <div v-if="editorMode === 'html'">
                                    <!-- HTML Editor Toolbar -->
                                    <div class="mb-2 flex flex-wrap gap-2 p-2 bg-gray-50 border border-gray-300 rounded-t-md">
                                        <button
                                            type="button"
                                            @click="formatText('bold')"
                                            class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                            title="Bold"
                                        >
                                            <strong>B</strong>
                                        </button>
                                        <button
                                            type="button"
                                            @click="formatText('italic')"
                                            class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                            title="Italic"
                                        >
                                            <em>I</em>
                                        </button>
                                        <button
                                            type="button"
                                            @click="formatText('underline')"
                                            class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                            title="Underline"
                                        >
                                            <u>U</u>
                                        </button>
                                        <div class="border-l border-gray-300 mx-1"></div>
                                        <button
                                            type="button"
                                            @click="formatText('h1')"
                                            class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                            title="Heading 1"
                                        >
                                            H1
                                        </button>
                                        <button
                                            type="button"
                                            @click="formatText('h2')"
                                            class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                            title="Heading 2"
                                        >
                                            H2
                                        </button>
                                        <button
                                            type="button"
                                            @click="formatText('h3')"
                                            class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                            title="Heading 3"
                                        >
                                            H3
                                        </button>
                                        <div class="border-l border-gray-300 mx-1"></div>
                                        <button
                                            type="button"
                                            @click="formatText('ul')"
                                            class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                            title="Unordered List"
                                        >
                                            • List
                                        </button>
                                        <button
                                            type="button"
                                            @click="formatText('ol')"
                                            class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                            title="Ordered List"
                                        >
                                            1. List
                                        </button>
                                        <button
                                            type="button"
                                            @click="insertLink"
                                            class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                            title="Insert Link"
                                        >
                                            🔗 Link
                                        </button>
                                        <div class="border-l border-gray-300 mx-1"></div>
                                        <button
                                            type="button"
                                            @click="insertVariable('name')"
                                            class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                            title="Insert Name Variable"
                                        >
                                            {name}
                                        </button>
                                        <button
                                            type="button"
                                            @click="insertVariable('company')"
                                            class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                            title="Insert Company Variable"
                                        >
                                            {company}
                                        </button>
                                        <button
                                            type="button"
                                            @click="insertVariable('email')"
                                            class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                            title="Insert Email Variable"
                                        >
                                            {email}
                                        </button>
                                        <button
                                            type="button"
                                            @click="insertVariable('phone')"
                                            class="px-3 py-1 text-sm bg-white border border-gray-300 rounded hover:bg-gray-100"
                                            title="Insert Phone Variable"
                                        >
                                            {phone}
                                        </button>
                                    </div>
                                    <textarea
                                        ref="htmlEditor"
                                        v-model="form.content"
                                        rows="12"
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-b-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                                        placeholder="Enter HTML content or use the toolbar above..."
                                    ></textarea>
                                </div>
                                
                                <div v-else class="border border-gray-300 rounded-md bg-white min-h-[200px] overflow-auto">
                                    <div 
                                        class="p-4 email-preview" 
                                        v-html="form.content || '<p class=&quot;text-gray-400 italic&quot;>No content to preview. Start typing HTML in the editor above.</p>'"
                                    ></div>
                                </div>
                            </div>

                            <!-- Simple Textarea for WhatsApp -->
                            <div v-else>
                                <textarea
                                    v-model="form.content"
                                    rows="8"
                                    required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    placeholder="Enter message content..."
                                ></textarea>
                            </div>

                            <p class="mt-1 text-xs text-gray-500">
                                Available variables: {{ '{name}' }}, {{ '{company}' }}, {{ '{email}' }}, {{ '{phone}' }}
                            </p>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button
                                type="button"
                                @click="closeModal"
                                class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            >
                                {{ form.processing ? 'Saving...' : 'Save Template' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    templates: Array,
});

const showAddModal = ref(false);
const editingTemplate = ref(null);
const editorMode = ref('html');
const imagePreview = ref(null);
const htmlEditor = ref(null);

const form = useForm({
    name: '',
    type: '',
    subject: '',
    content: '',
    image: null,
});

const handleTypeChange = () => {
    if (form.type === 'whatsapp') {
        form.subject = '';
    }
    editorMode.value = 'html';
};

const handleImageChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        form.image = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const editTemplate = (template) => {
    editingTemplate.value = template;
    form.name = template.name;
    form.type = template.type;
    form.subject = template.subject || '';
    form.content = template.content;
    form.image = null;
    imagePreview.value = null;
    editorMode.value = 'html';
    showAddModal.value = true;
};

const deleteTemplate = (template) => {
    if (confirm(`Are you sure you want to delete "${template.name}"?`)) {
        router.delete(route('campaign-templates.destroy', template.id), {
            preserveState: true,
            preserveScroll: true,
        });
    }
};

const saveTemplate = () => {
    if (editingTemplate.value) {
        form.put(route('campaign-templates.update', editingTemplate.value.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
            },
        });
    } else {
        form.post(route('campaign-templates.store'), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
            },
        });
    }
};

const formatText = (command) => {
    const textarea = htmlEditor.value;
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = form.content.substring(start, end);
    let replacement = '';
    
    switch (command) {
        case 'bold':
            replacement = `<strong>${selectedText || 'bold text'}</strong>`;
            break;
        case 'italic':
            replacement = `<em>${selectedText || 'italic text'}</em>`;
            break;
        case 'underline':
            replacement = `<u>${selectedText || 'underlined text'}</u>`;
            break;
        case 'h1':
            replacement = `<h1>${selectedText || 'Heading 1'}</h1>`;
            break;
        case 'h2':
            replacement = `<h2>${selectedText || 'Heading 2'}</h2>`;
            break;
        case 'h3':
            replacement = `<h3>${selectedText || 'Heading 3'}</h3>`;
            break;
        case 'ul':
            replacement = `<ul><li>${selectedText || 'List item'}</li></ul>`;
            break;
        case 'ol':
            replacement = `<ol><li>${selectedText || 'List item'}</li></ol>`;
            break;
    }
    
    form.content = form.content.substring(0, start) + replacement + form.content.substring(end);
    
    // Restore cursor position
    setTimeout(() => {
        textarea.focus();
        const newPos = start + replacement.length;
        textarea.setSelectionRange(newPos, newPos);
    }, 0);
};

const insertLink = () => {
    const textarea = htmlEditor.value;
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = form.content.substring(start, end);
    const linkText = selectedText || 'link text';
    const url = prompt('Enter URL:', 'https://');
    
    if (url) {
        const replacement = `<a href="${url}">${linkText}</a>`;
        form.content = form.content.substring(0, start) + replacement + form.content.substring(end);
        
        setTimeout(() => {
            textarea.focus();
            const newPos = start + replacement.length;
            textarea.setSelectionRange(newPos, newPos);
        }, 0);
    }
};

const insertVariable = (varName) => {
    const textarea = htmlEditor.value;
    if (!textarea) return;
    
    const start = textarea.selectionStart;
    const replacement = `{${varName}}`;
    
    form.content = form.content.substring(0, start) + replacement + form.content.substring(start);
    
    setTimeout(() => {
        textarea.focus();
        const newPos = start + replacement.length;
        textarea.setSelectionRange(newPos, newPos);
    }, 0);
};

const closeModal = () => {
    showAddModal.value = false;
    editingTemplate.value = null;
    form.reset();
    form.type = '';
    imagePreview.value = null;
    editorMode.value = 'html';
};
</script>
