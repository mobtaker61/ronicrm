<template>
    <AppLayout>
        <template #header>
            {{ t('campaigns.create_campaign') }}
        </template>

        <div class="max-w-4xl mx-auto">
            <form @submit.prevent="submit" class="bg-white rounded-lg shadow p-6 space-y-6" enctype="multipart/form-data">
                <!-- Success/Error Messages -->
                <div v-if="form.errors" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                    <ul class="list-disc list-inside">
                        <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                    </ul>
                </div>

                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('campaigns.name_required') }}</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            :placeholder="t('campaigns.name_placeholder')"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('campaigns.type_required') }}</label>
                        <select
                            v-model="form.type"
                            required
                            @change="handleTypeChange"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">{{ t('campaigns.select_type') }}</option>
                            <option value="whatsapp">{{ t('campaigns.type_whatsapp') }}</option>
                            <option value="email">{{ t('campaigns.type_email') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('sidebar.templates') }}</label>
                        <select
                            v-model="form.template_id"
                            @change="loadTemplate"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option :value="null">{{ t('campaigns.no_template') }}</option>
                            <option v-for="template in templates" :key="template.id" :value="template.id">
                                {{ template.name }}
                            </option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('common.description') }}</label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            :placeholder="t('campaigns.description_placeholder')"
                        ></textarea>
                    </div>
                </div>

                <!-- Email Subject (only for email campaigns) -->
                <div v-if="form.type === 'email'" class="border-t pt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('campaigns.email_subject_required') }}</label>
                    <input
                        v-model="form.subject"
                        type="text"
                        :required="form.type === 'email'"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        :placeholder="t('campaigns.email_subject_placeholder')"
                    />
                </div>

                <!-- Email Attachments -->
                <div v-if="form.type === 'email'" class="border-t pt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('campaigns.email_attachment_optional') }}</label>
                    <input
                        type="file"
                        ref="emailAttachmentsInput"
                        multiple
                        @change="handleEmailAttachmentsChange"
                        class="block w-full text-sm text-gray-500 file:ltr:mr-4 file:rtl:ml-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                    />
                    <p class="mt-1 text-xs text-gray-500">{{ t('campaigns.email_attachments_help') }}</p>
                    <ul v-if="emailAttachmentFiles.length" class="mt-2 space-y-1">
                        <li v-for="(f, i) in emailAttachmentFiles" :key="i" class="flex items-center justify-between text-sm text-gray-600">
                            <span>{{ f.name }} ({{ formatFileSize(f.size) }})</span>
                            <button type="button" @click="removeEmailAttachment(i)" class="text-red-600 hover:text-red-800">{{ t('common.delete') }}</button>
                        </li>
                    </ul>
                </div>

                <!-- File Upload (for WhatsApp) -->
                <div v-if="form.type === 'whatsapp'" class="border-t pt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('campaigns.attachment_optional') }}</label>
                    <div class="flex items-center space-x-4 rtl:space-x-reverse">
                        <div v-if="imagePreview || selectedFile" class="flex-shrink-0 relative">
                            <img
                                v-if="imagePreview && isImageFile(selectedFile)"
                                :src="imagePreview"
                                :alt="t('common.preview')"
                                class="w-32 h-32 object-cover rounded-lg border border-gray-300"
                            />
                            <div
                                v-else-if="selectedFile"
                                class="w-32 h-32 bg-gray-100 rounded-lg border border-gray-300 flex items-center justify-center"
                            >
                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <button
                                v-if="imagePreview || selectedFile"
                                type="button"
                                @click="clearImage"
                                class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600"
                            >
                                ×
                            </button>
                        </div>
                        <div class="flex-1">
                            <input
                                type="file"
                                @change="handleImageChange"
                                class="block w-full text-sm text-gray-500 file:ltr:mr-4 file:rtl:ml-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                            />
                            <button
                                type="button"
                                @click="showMediaPicker = true"
                                class="mt-2 px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200"
                            >
                                {{ t('campaigns.select_from_media') }}
                            </button>
                            <p class="mt-1 text-xs text-gray-500">{{ t('campaigns.whatsapp_attachment_help') }}</p>
                            <p v-if="selectedFile" class="mt-1 text-xs text-gray-600">
                                {{ t('campaigns.selected_file') }}: {{ selectedFile.name }}{{ selectedFile.size != null ? ' (' + formatFileSize(selectedFile.size) + ')' : '' }}
                            </p>
                            <p v-if="form.template_id && selectedTemplate?.image" class="mt-1 text-xs text-blue-600">
                                {{ t('campaigns.template_has_attachment') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <div class="border-t pt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('campaigns.message_content_required') }}</label>
                    
                    <!-- HTML Editor for Email -->
                    <div v-if="form.type === 'email'" class="space-y-2">
                        <div class="flex space-x-2 rtl:space-x-reverse mb-2">
                            <button
                                type="button"
                                @click="editorMode = 'html'"
                                :class="[
                                    'px-3 py-1 text-sm rounded-md',
                                    editorMode === 'html' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'
                                ]"
                            >
                                {{ t('campaigns.html') }}
                            </button>
                            <button
                                type="button"
                                @click="editorMode = 'preview'"
                                :class="[
                                    'px-3 py-1 text-sm rounded-md',
                                    editorMode === 'preview' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700'
                                ]"
                            >
                                {{ t('common.preview') }}
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
                                <div class="border-l border-gray-300 mx-1 rtl:border-l-0 rtl:border-r"></div>
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
                                <div class="border-l border-gray-300 mx-1 rtl:border-l-0 rtl:border-r"></div>
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
                                <div class="border-l border-gray-300 mx-1 rtl:border-l-0 rtl:border-r"></div>
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
                                :placeholder="t('campaigns.html_placeholder')"
                            ></textarea>
                        </div>
                        
                        <div v-else class="border border-gray-300 rounded-md bg-white min-h-[200px] overflow-auto">
                            <div 
                                class="p-4 email-preview" 
                                v-html="previewContent"
                            ></div>
                        </div>
                    </div>

                    <!-- Simple Textarea for WhatsApp -->
                    <div v-else class="space-y-2">
                        <div
                            v-if="form.type === 'whatsapp'"
                            class="flex flex-wrap gap-2 p-2 bg-gray-50 border border-gray-200 rounded-t-md border-b-0"
                        >
                            <span class="text-xs text-gray-500 self-center ltr:mr-1 rtl:ml-1">{{ t('campaigns.insert_label') }}</span>
                            <button
                                v-for="v in waVariableNames"
                                :key="v"
                                type="button"
                                @click="insertWaVariable(v)"
                                class="px-2 py-1 text-xs bg-white border border-gray-300 rounded hover:bg-gray-100"
                            >
                                {{ '{' + v + '}' }}
                            </button>
                        </div>
                        <textarea
                            ref="waCampaignBody"
                            v-model="form.content"
                            rows="8"
                            required
                            :class="[
                                'w-full px-3 py-2 border border-gray-300 focus:outline-none focus:ring-2 focus:ring-blue-500',
                                form.type === 'whatsapp' ? 'rounded-b-md rounded-t-none border-t-0' : 'rounded-md',
                            ]"
                            :placeholder="t('campaigns.whatsapp_content_placeholder')"
                        ></textarea>
                    </div>

                    <p class="mt-1 text-xs text-gray-500">
                        <template v-if="form.type === 'whatsapp'">
                            {{ t('campaigns.variables_whatsapp') }}: {{ '{name}' }}, {{ '{company}' }}, {{ '{email}' }}, {{ '{phone}' }}, {{ '{gender}' }}, {{ '{intro}' }}
                        </template>
                        <template v-else>
                            {{ t('campaigns.variables_email') }}: {{ '{name}' }}, {{ '{company}' }}, {{ '{email}' }}, {{ '{phone}' }}
                        </template>
                    </p>

                    <!-- همان منطق تمپلیت: برای کمپین بدون تمپلیت یا ویرایش دستی -->
                    <div
                        v-if="form.type === 'whatsapp'"
                        class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/60 p-5 space-y-4"
                    >
                        <h4 class="text-sm font-semibold text-emerald-900">{{ t('campaigns.whatsapp_message_settings') }}</h4>
                        <p class="text-xs text-emerald-900/80">
                            {{ t('campaigns.whatsapp_message_settings_help') }}
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('campaigns.gender_male_label_for_variable') }} {{ '{gender}' }}</label>
                                <input
                                    v-model="form.whatsapp_settings.gender_labels.male"
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                    :placeholder="t('campaigns.gender_male_placeholder')"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('campaigns.gender_female_label') }}</label>
                                <input
                                    v-model="form.whatsapp_settings.gender_labels.female"
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                    :placeholder="t('campaigns.gender_female_placeholder')"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">{{ t('campaigns.gender_other_label') }}</label>
                                <input
                                    v-model="form.whatsapp_settings.gender_labels.other"
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                    :placeholder="t('campaigns.gender_other_placeholder')"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('campaigns.intro_phrases_label') }} {{ '{intro}' }} {{ t('campaigns.comma_separated') }}</label>
                            <textarea
                                v-model="form.whatsapp_settings.intro_phrases"
                                rows="2"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                                :placeholder="t('campaigns.intro_phrases_placeholder')"
                            ></textarea>
                        </div>
                        <label class="flex items-start gap-3 cursor-pointer rounded-lg bg-white/80 border p-3">
                            <input
                                v-model="form.whatsapp_settings.append_random_token"
                                type="checkbox"
                                class="mt-1 rounded border-gray-300 text-emerald-600"
                            />
                            <span class="text-sm text-gray-800">
                                <span class="font-medium">{{ t('campaigns.append_random_token_label') }}</span>
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Recipients Selection -->
                <div class="border-t pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('campaigns.select_recipients') }}</h3>
                    
                    <!-- Campaign Type Warning -->
                    <div v-if="!form.type" class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <p class="text-sm text-yellow-800">
                            <strong>{{ t('common.note') }}:</strong> {{ t('campaigns.select_type_warning') }}
                        </p>
                    </div>
                    
                    <!-- Filtered Count Info -->
                    <div v-if="form.type" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <strong>{{ t('campaigns.by_campaign_type') }}:</strong>
                            <span class="font-semibold">{{ recipientEntries.length }}</span>
                            {{ form.type === 'whatsapp' ? t('campaigns.whatsapp_contacts_count_label') : t('campaigns.email_count_label') }}
                            ({{ t('campaigns.recipients_count_hint') }})
                        </p>
                    </div>
                    
                    <!-- Filter Options -->
                    <div class="mb-4 p-4 bg-gray-50 rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('campaigns.filter_by_project') }}</label>
                                <select
                                    v-model="recipientFilters.project_id"
                                    @change="filterRecipients"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">{{ t('customers.all_projects') }}</option>
                                    <option v-for="project in projects" :key="project.id" :value="project.id">
                                        {{ project.name }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('campaigns.filter_by_industry') }}</label>
                                <IndustrySelect
                                    v-model="recipientFilters.industry_id"
                                    :industries="industries"
                                    :placeholder="t('customers.all_industries')"
                                    @update:model-value="filterRecipients"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('campaigns.filter_by_status') }}</label>
                                <select
                                    v-model="recipientFilters.status"
                                    @change="filterRecipients"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">{{ t('customers.all_statuses') }}</option>
                                    <option value="lead">{{ t('customers.lead') }}</option>
                                    <option value="prospect">{{ t('customers.prospect') }}</option>
                                    <option value="customer">{{ t('customers.customer') }}</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('campaigns.filter_by_type') }}</label>
                                <select
                                    v-model="recipientFilters.type"
                                    @change="filterRecipients"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="">{{ t('customers.all_types') }}</option>
                                    <option value="person">Person</option>
                                    <option value="company">Company</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between">
                            <div class="flex items-center space-x-4 rtl:space-x-reverse">
                                <button
                                    type="button"
                                    @click="selectAll"
                                    class="px-3 py-1 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
                                >
                                    {{ t('campaigns.select_all') }}
                                </button>
                                <button
                                    type="button"
                                    @click="deselectAll"
                                    class="px-3 py-1 text-sm bg-gray-600 text-white rounded-md hover:bg-gray-700"
                                >
                                    {{ t('campaigns.deselect_all') }}
                                </button>
                            </div>
                            <div class="text-sm text-gray-600">
                                {{ t('campaigns.selected_messages') }}: <span class="font-semibold">{{ form.recipient_entries.length }}</span> ({{ t('campaigns.messages_to_send') }})
                            </div>
                        </div>
                    </div>

                    <!-- Recipients List (one row per contact method) -->
                    <div v-if="!form.type" class="p-8 text-center text-gray-500 border border-gray-200 rounded-lg">
                        <p>{{ t('campaigns.select_type_first_for_recipients') }}</p>
                    </div>
                    <div v-else-if="recipientEntries.length === 0" class="p-8 text-center text-gray-500 border border-gray-200 rounded-lg">
                        <p>{{ t('campaigns.no_recipients_for_filters') }}</p>
                        <p class="text-sm mt-2">
                            {{ t('campaigns.adjust_filters_hint') }}
                            {{ form.type === 'whatsapp' ? t('campaigns.type_whatsapp') : t('campaigns.type_email') }}
                            {{ t('campaigns.contact_suffix_hint') }}
                        </p>
                    </div>
                    <div v-else class="max-h-96 overflow-y-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">
                                        <input
                                            type="checkbox"
                                            :checked="allSelected"
                                            @change="toggleAll"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                    </th>
                                    <th class="px-4 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('common.name') }}</th>
                                    <th class="px-4 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('common.type') }}</th>
                                    <th class="px-4 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('campaigns.contact_to_send') }}</th>
                                    <th class="px-4 py-3 text-left rtl:text-right text-xs font-medium text-gray-500 uppercase">{{ t('common.status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="entry in recipientEntries" :key="entry.key">
                                    <td class="px-4 py-3">
                                        <input
                                            type="checkbox"
                                            :checked="isEntrySelected(entry)"
                                            @change="toggleEntry(entry)"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ entry.customer.name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <span class="px-2 py-1 text-xs rounded-full"
                                            :class="entry.customer.type === 'person' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'"
                                        >
                                            {{ customerTypeLabel(entry.customer.type) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <span class="text-xs text-gray-400">{{ contactTypeLabel(entry.contact ? entry.contact.type : 'email') }}:</span> {{ entry.contact ? entry.contact.value : entry.customer.email }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <span class="px-2 py-1 text-xs rounded-full"
                                            :class="{
                                                'bg-yellow-100 text-yellow-800': entry.customer.status === 'lead',
                                                'bg-blue-100 text-blue-800': entry.customer.status === 'prospect',
                                                'bg-green-100 text-green-800': entry.customer.status === 'customer',
                                            }"
                                        >
                                            {{ customerStatusLabel(entry.customer.status) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Scheduling -->
                <div class="border-t pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ t('campaigns.schedule_campaign') }}</h3>
                    <div class="flex items-center space-x-4 rtl:space-x-reverse">
                        <label class="flex items-center space-x-2 rtl:space-x-reverse">
                            <input
                                v-model="scheduleNow"
                                type="radio"
                                :value="true"
                                @change="form.scheduled_at = null"
                                class="text-blue-600 focus:ring-blue-500"
                            />
                            <span>{{ t('campaigns.send_now') }}</span>
                        </label>
                        <label class="flex items-center space-x-2 rtl:space-x-reverse">
                            <input
                                v-model="scheduleNow"
                                type="radio"
                                :value="false"
                                class="text-blue-600 focus:ring-blue-500"
                            />
                            <span>{{ t('campaigns.schedule_later') }}</span>
                        </label>
                    </div>
                    <div v-if="!scheduleNow" class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('campaigns.scheduled_datetime') }}</label>
                        <input
                            v-model="form.scheduled_at"
                            type="datetime-local"
                            class="w-full md:w-1/2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex justify-end space-x-3 rtl:space-x-reverse border-t pt-6">
                    <Link
                        :href="route('campaigns.index')"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                    >
                        {{ t('common.cancel') }}
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing || form.recipient_entries.length === 0"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? t('campaigns.creating') : t('campaigns.create_campaign') }}
                    </button>
                </div>
            </form>
        </div>

        <MediaPickerModal
            :show="showMediaPicker"
            @close="showMediaPicker = false"
            @select="onMediaSelectForCampaign"
        />
    </AppLayout>
</template>

<script setup>
import { ref, computed, nextTick, watch } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import MediaPickerModal from '@/Components/MediaPickerModal.vue';
import IndustrySelect from '@/Components/IndustrySelect.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useI18n } from '@/composables/useI18n';

const { t } = useI18n();

const customerTypeLabel = (type) => {
    if (type === 'person') return t('customers.person');
    if (type === 'company') return t('customers.company');
    return type || t('common.dash');
};

const customerStatusLabel = (status) => {
    if (status === 'lead') return t('customers.lead');
    if (status === 'prospect') return t('customers.prospect');
    if (status === 'customer') return t('customers.customer');
    return status || t('common.dash');
};

const contactTypeLabel = (type) => {
    if (type === 'email') return t('common.email');
    if (type === 'whatsapp') return t('inbox.whatsapp');
    // Fallback: show raw type if we don't have a dedicated label.
    return type || t('common.dash');
};

const props = defineProps({
    templates: Array,
    industries: Array,
    projects: Array,
    customers: Array,
});

// پیدا کردن صنعت از درخت با id (برای فیلتر مخاطبان)
function findIndustryById(items, id) {
    if (!id) return null;
    for (const item of items || []) {
        if (item.id == id) return item;
        const found = findIndustryById(item.children, id);
        if (found) return found;
    }
    return null;
}

function defaultWhatsappSettings() {
    return {
        gender_labels: { male: '', female: '', other: '' },
        intro_phrases: '',
        append_random_token: false,
    };
}

const waVariableNames = ['name', 'company', 'email', 'phone', 'gender', 'intro'];
const waCampaignBody = ref(null);

const form = useForm({
    name: '',
    description: '',
    type: '',
    template_id: null,
    subject: '',
    content: '',
    image: null,
    image_path: null,
    attachments: [],
    scheduled_at: null,
    recipient_entries: [],
    whatsapp_settings: null,
});
const emailAttachmentsInput = ref(null);
const emailAttachmentFiles = ref([]);

watch(
    () => form.type,
    (t) => {
        if (t === 'whatsapp' && !form.whatsapp_settings) {
            form.whatsapp_settings = defaultWhatsappSettings();
        }
    }
);

// برای پیش‌نمایش: اگر محتوا شبیه HTML نبود (بدون تگ)، خط‌شکنی را با <br> نشان بده تا به‌هم نریزد
const previewContent = computed(() => {
    const c = form.content || '';
    if (!c.trim()) return `<p class="text-gray-400 italic">${t('campaigns.no_preview_content')}</p>`;
    if (c.includes('<') && c.includes('>')) return c;
    return c.replace(/\n/g, '<br>');
});

const scheduleNow = ref(true);
const editorMode = ref('html');
const imagePreview = ref(null);
const selectedFile = ref(null);
const showMediaPicker = ref(false);
const recipientFilters = ref({
    project_id: '',
    industry_id: '',
    status: '',
    type: '',
});

const filteredCustomers = computed(() => {
    let filtered = [...props.customers];

    // Filter by campaign type (WhatsApp or Email)
    if (form.type === 'whatsapp') {
        // Only customers with WhatsApp contact (whatsapp type in contacts)
        filtered = filtered.filter(c => {
            // Check if customer has whatsapp contact
            if (c.contacts && Array.isArray(c.contacts)) {
                return c.contacts.some(contact => contact.type === 'whatsapp');
            }
            
            return false;
        });
    } else if (form.type === 'email') {
        // Only customers with email (either in email field or contacts)
        filtered = filtered.filter(c => {
            // Check if customer has email in main field
            if (c.email) return true;
            
            // Check if customer has email in contacts
            if (c.contacts && Array.isArray(c.contacts)) {
                return c.contacts.some(contact => contact.type === 'email');
            }
            
            return false;
        });
    }

    // Filter by industry (دسته/زیردسته و شامل زیردسته‌ها)
    if (recipientFilters.value.industry_id) {
        const selectedIndustry = findIndustryById(props.industries || [], recipientFilters.value.industry_id);
        const industryIds = [parseInt(recipientFilters.value.industry_id)];
        const getChildIds = (industry) => {
            (industry?.children || []).forEach(child => {
                industryIds.push(child.id);
                getChildIds(child);
            });
        };
        if (selectedIndustry) getChildIds(selectedIndustry);
        filtered = filtered.filter(c => industryIds.includes(c.industry_id));
    }

    // Filter by status
    if (recipientFilters.value.status) {
        filtered = filtered.filter(c => c.status === recipientFilters.value.status);
    }

    // Filter by customer type
    if (recipientFilters.value.type) {
        filtered = filtered.filter(c => c.type === recipientFilters.value.type);
    }

    // Filter by project
    if (recipientFilters.value.project_id) {
        filtered = filtered.filter(c => (c.project_id || '') === recipientFilters.value.project_id || c.project_id === parseInt(recipientFilters.value.project_id));
    }

    return filtered;
});

// One row per contact method matching campaign type (WhatsApp -> whatsapp contacts, Email -> email contacts + main email)
const recipientEntries = computed(() => {
    const list = [];
    const custs = filteredCustomers.value;
    if (form.type === 'whatsapp') {
        custs.forEach(c => {
            if (c.contacts && Array.isArray(c.contacts)) {
                c.contacts.filter(contact => contact.type === 'whatsapp').forEach(contact => {
                    list.push({
                        key: `${c.id}-${contact.id}`,
                        customer: c,
                        contact,
                        customer_id: c.id,
                        customer_contact_id: contact.id,
                    });
                });
            }
        });
    } else if (form.type === 'email') {
        custs.forEach(c => {
            const emailContacts = (c.contacts && Array.isArray(c.contacts)) ? c.contacts.filter(contact => contact.type === 'email') : [];
            emailContacts.forEach(contact => {
                list.push({
                    key: `${c.id}-${contact.id}`,
                    customer: c,
                    contact,
                    customer_id: c.id,
                    customer_contact_id: contact.id,
                });
            });
            if (c.email && emailContacts.length === 0) {
                list.push({
                    key: `${c.id}-main`,
                    customer: c,
                    contact: null,
                    customer_id: c.id,
                    customer_contact_id: null,
                });
            }
        });
    }
    return list;
});

const allSelected = computed(() => {
    return recipientEntries.value.length > 0 &&
           recipientEntries.value.every(entry => isEntrySelected(entry));
});

function isEntrySelected(entry) {
    return form.recipient_entries.some(
        e => e.customer_id === entry.customer_id && (e.customer_contact_id || null) === (entry.customer_contact_id || null)
    );
}

function toggleEntry(entry) {
    const idx = form.recipient_entries.findIndex(
        e => e.customer_id === entry.customer_id && (e.customer_contact_id || null) === (entry.customer_contact_id || null)
    );
    if (idx >= 0) {
        form.recipient_entries.splice(idx, 1);
    } else {
        form.recipient_entries.push({
            customer_id: entry.customer_id,
            customer_contact_id: entry.customer_contact_id || null,
        });
    }
}

const handleTypeChange = () => {
    if (form.type === 'whatsapp') {
        form.subject = '';
        form.attachments = [];
        emailAttachmentFiles.value = [];
        if (emailAttachmentsInput.value) emailAttachmentsInput.value.value = '';
        if (!form.whatsapp_settings) {
            form.whatsapp_settings = defaultWhatsappSettings();
        }
    } else if (form.type === 'email') {
        form.image = null;
        imagePreview.value = null;
        selectedFile.value = null;
        form.whatsapp_settings = null;
    }
    editorMode.value = 'html';
    // Clear selected recipients when campaign type changes
    form.recipient_entries = [];
};

const handleImageChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        // Validate file size (50MB max)
        if (file.size > 50 * 1024 * 1024) {
            alert(t('campaigns.file_size_max_50mb'));
            event.target.value = '';
            return;
        }
        
        form.image = file;
        form.image_path = null;
        selectedFile.value = file;
        
        // Only create preview for image files
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.value = e.target.result;
            };
            reader.readAsDataURL(file);
        } else {
            imagePreview.value = null;
        }
    }
};

const onMediaSelectForCampaign = (file) => {
    form.image_path = file.path;
    form.image = null;
    selectedFile.value = { name: file.name, url: file.url, isImage: file.is_image };
    let fullUrl = file.url;
    if (!file.url.startsWith('http')) {
        fullUrl = window.location.origin + (file.url.startsWith('/') ? file.url : '/' + file.url);
    }
    imagePreview.value = file.is_image ? fullUrl : null;
};

const clearImage = () => {
    form.image = null;
    form.image_path = null;
    imagePreview.value = null;
    selectedFile.value = null;
    const fileInput = document.querySelector('input[type="file"]');
    if (fileInput) {
        fileInput.value = '';
    }
};

const isImageFile = (file) => {
    if (!file) return false;
    if (file.isImage !== undefined) return file.isImage;
    return file.type && file.type.startsWith('image/');
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
};

const selectedTemplate = computed(() => {
    if (form.template_id) {
        return props.templates.find(t => t.id === form.template_id);
    }
    return null;
});

const loadTemplate = () => {
    if (form.template_id) {
        const template = props.templates.find(t => t.id === form.template_id);
        if (template) {
            form.content = template.content || '';
            form.subject = template.subject || '';
            form.image_path = null;
            selectedFile.value = null;
            if (form.type === 'whatsapp') {
                form.whatsapp_settings =
                    template.whatsapp_settings && typeof template.whatsapp_settings === 'object'
                        ? JSON.parse(JSON.stringify(template.whatsapp_settings))
                        : defaultWhatsappSettings();
            }
            // Only show template image if type matches and no new image is uploaded
            if (template.image && form.type === 'whatsapp' && !form.image) {
                // Template image is already a full URL from backend
                imagePreview.value = template.image;
            } else if (!form.image) {
                imagePreview.value = null;
            }
        }
    } else {
        if (!form.image) {
            imagePreview.value = null;
        }
        if (form.type === 'whatsapp') {
            form.whatsapp_settings = defaultWhatsappSettings();
        }
    }
};

const filterRecipients = () => {
    // Filtering is handled by computed property
};

const selectAll = () => {
    recipientEntries.value.forEach(entry => {
        if (!isEntrySelected(entry)) {
            form.recipient_entries.push({
                customer_id: entry.customer_id,
                customer_contact_id: entry.customer_contact_id || null,
            });
        }
    });
};

const deselectAll = () => {
    form.recipient_entries = [];
};

const toggleAll = (event) => {
    if (event.target.checked) {
        selectAll();
    } else {
        deselectAll();
    }
};

const htmlEditor = ref(null);

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

const insertWaVariable = (varName) => {
    const el = waCampaignBody.value;
    const replacement = `{${varName}}`;
    if (el && typeof el.selectionStart === 'number') {
        const start = el.selectionStart;
        form.content = form.content.substring(0, start) + replacement + form.content.substring(start);
        nextTick(() => {
            el.focus();
            const pos = start + replacement.length;
            el.setSelectionRange(pos, pos);
        });
    } else {
        form.content = (form.content || '') + replacement;
    }
};

function handleEmailAttachmentsChange(event) {
    const files = event.target.files;
    if (files && files.length) {
        form.attachments = Array.from(files);
        emailAttachmentFiles.value = Array.from(files);
    }
}
function removeEmailAttachment(index) {
    form.attachments.splice(index, 1);
    emailAttachmentFiles.value.splice(index, 1);
    if (emailAttachmentsInput.value) emailAttachmentsInput.value.value = '';
}

const submit = () => {
    form.transform((data) => {
        const payload = { ...data };
        if (payload.recipient_entries && Array.isArray(payload.recipient_entries)) {
            payload.recipient_entries = JSON.stringify(payload.recipient_entries);
        }
        if (payload.type === 'whatsapp' && payload.whatsapp_settings && typeof payload.whatsapp_settings === 'object') {
            payload.whatsapp_settings = JSON.stringify(payload.whatsapp_settings);
        } else {
            delete payload.whatsapp_settings;
        }
        return payload;
    }).post(route('campaigns.store'), {
        forceFormData: true,
    });
};
</script>

<style scoped>
.email-preview :deep(p) { margin-bottom: 0.75rem; line-height: 1.6; }
.email-preview :deep(p:last-child) { margin-bottom: 0; }
.email-preview :deep(a) { color: #2563eb; text-decoration: underline; }
.email-preview :deep(ul), .email-preview :deep(ol) { margin: 0.5rem 0; padding-left: 1.5rem; line-height: 1.6; }
.email-preview :deep(li) { margin-bottom: 0.25rem; }
.email-preview :deep(h1), .email-preview :deep(h2), .email-preview :deep(h3) { margin-top: 1rem; margin-bottom: 0.5rem; font-weight: 600; }
.email-preview :deep(h1) { font-size: 1.25rem; }
.email-preview :deep(h2) { font-size: 1.125rem; }
.email-preview :deep(h3) { font-size: 1rem; }
</style>
