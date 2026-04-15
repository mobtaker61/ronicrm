<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FrontController;
use Illuminate\Support\Facades\Route;

// Front (public, no login)
Route::get('/', [FrontController::class, 'welcome'])->name('front.welcome');
Route::get('/privacy-policy', [FrontController::class, 'privacy'])->name('front.privacy');
Route::get('/terms-and-conditions', [FrontController::class, 'terms'])->name('front.terms');

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/register', [\App\Http\Controllers\Auth\RegisterController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('register');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::get('/forgot-password', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [\App\Http\Controllers\Auth\ForgotPasswordController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('password.email');
Route::get('/reset-password/{token}', [\App\Http\Controllers\Auth\ResetPasswordController::class, 'create'])->name('password.reset');
Route::post('/reset-password', [\App\Http\Controllers\Auth\ResetPasswordController::class, 'store'])->name('password.store');

// Public customer card
Route::get('/c/{shareKey}', [\App\Http\Controllers\PublicCustomerCardController::class, 'show'])->name('public.customer.card');
Route::post('/c/{shareKey}/share-via-whatsapp', [\App\Http\Controllers\PublicCustomerCardController::class, 'shareViaWhatsApp'])->name('public.customer.share-via-whatsapp');

// Public project share (no auth)
Route::get('/p/{shareToken}', [\App\Http\Controllers\PublicProjectShareController::class, 'show'])->name('public.project.share');
Route::get('/p/{shareToken}/customer/{shareKey}', [\App\Http\Controllers\PublicProjectShareController::class, 'getCustomer'])->name('public.project.customer');
Route::get('/p/{shareToken}/export-excel', [\App\Http\Controllers\PublicProjectShareController::class, 'exportExcel'])->name('public.project.export-excel');

// Webhooks (no auth, no CSRF – called by Telegram, Ronibot, Meta)
Route::post('/wpwebhook', [\App\Http\Controllers\RonibotWebhookController::class, 'handle'])->name('ronibot.webhook');
Route::post('/wpwebhook-group', [\App\Http\Controllers\RonibotWebhookController::class, 'groupSync'])->name('ronibot.webhook.group');
Route::post('/telegram-webhook', [\App\Http\Controllers\TelegramWebhookController::class, 'handle'])->name('telegram.webhook');
Route::post('/telegram-webhook/{organization}', [\App\Http\Controllers\TelegramWebhookController::class, 'handle'])->name('telegram.webhook.organization');
Route::get('/instagram-webhook', [\App\Http\Controllers\InstagramWebhookController::class, 'verify'])->name('instagram.webhook.verify');
Route::post('/instagram-webhook', [\App\Http\Controllers\InstagramWebhookController::class, 'handle'])->middleware('throttle:120,1')->name('instagram.webhook');
Route::get('/instagram-webhook/{organization}', [\App\Http\Controllers\InstagramWebhookController::class, 'verify'])->name('instagram.webhook.verify.organization');
Route::post('/instagram-webhook/{organization}', [\App\Http\Controllers\InstagramWebhookController::class, 'handle'])->middleware('throttle:120,1')->name('instagram.webhook.organization');

Route::get('/tiktok-webhook', [\App\Http\Controllers\TikTokWebhookController::class, 'verify'])->name('tiktok.webhook.verify');
Route::post('/tiktok-webhook', [\App\Http\Controllers\TikTokWebhookController::class, 'handle'])->middleware('throttle:120,1')->name('tiktok.webhook');

// i18n (public JSON + session locale setter)
Route::get('/i18n/{locale}.json', [\App\Http\Controllers\I18nController::class, 'json'])->name('i18n.json');
Route::post('/locale', [\App\Http\Controllers\PublicLocaleController::class, 'update'])->name('locale.set');
Route::post('/i18n/locale', [\App\Http\Controllers\I18nController::class, 'setLocale'])->middleware('auth')->name('i18n.locale.set');

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

Route::get('/email/verify/{id}/{hash}', [\App\Http\Controllers\Auth\EmailVerificationController::class, 'verify'])
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/organizations/current', [\App\Http\Controllers\CurrentOrganizationController::class, 'update'])->name('organizations.current.update');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [\App\Http\Controllers\ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // Industries
    Route::resource('industries', \App\Http\Controllers\IndustryController::class)->except(['show', 'create', 'edit']);

    // Projects
    Route::get('/projects', [\App\Http\Controllers\ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [\App\Http\Controllers\ProjectController::class, 'store'])->name('projects.store');
    Route::put('/projects/{project}', [\App\Http\Controllers\ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [\App\Http\Controllers\ProjectController::class, 'destroy'])->name('projects.destroy');

    // Customers
    Route::resource('customers', \App\Http\Controllers\CustomerController::class);
    Route::patch('customers/{customer}/quick-update', [\App\Http\Controllers\CustomerController::class, 'quickUpdate'])->name('customers.quick-update');
    Route::post('customers/import', [\App\Http\Controllers\CustomerController::class, 'import'])->name('customers.import');
    Route::post('customers/import-preview', [\App\Http\Controllers\CustomerController::class, 'importPreview'])->name('customers.import-preview');
    Route::post('customers/{customer}/notes', [\App\Http\Controllers\CustomerNoteController::class, 'store'])->name('customers.notes.store');
    Route::delete('customers/notes/{note}', [\App\Http\Controllers\CustomerNoteController::class, 'destroy'])->name('customers.notes.destroy');
    Route::post('customers/{customer}/contacts', [\App\Http\Controllers\CustomerContactController::class, 'store'])->name('customers.contacts.store');
    Route::put('customers/{customer}/contacts/{contact}', [\App\Http\Controllers\CustomerContactController::class, 'update'])->name('customers.contacts.update');
    Route::delete('customers/{customer}/contacts/{contact}', [\App\Http\Controllers\CustomerContactController::class, 'destroy'])->name('customers.contacts.destroy');
    Route::post('customers/{customer}/share-via-whatsapp', [\App\Http\Controllers\CustomerController::class, 'shareViaWhatsApp'])->name('customers.share-via-whatsapp');

    // Campaigns
    Route::resource('campaigns', \App\Http\Controllers\CampaignController::class)->except(['edit', 'update']);
    Route::post('campaigns/{campaign}/start', [\App\Http\Controllers\CampaignController::class, 'start'])->name('campaigns.start');
    Route::get('campaigns/{campaign}/status', [\App\Http\Controllers\CampaignController::class, 'getStatus'])->name('campaigns.status');
    Route::resource('campaign-templates', \App\Http\Controllers\CampaignTemplateController::class)->except(['show', 'create', 'edit']);

    // Telegram Group Crawler
    Route::get('/telegram-crawler', [\App\Http\Controllers\TelegramCrawlerController::class, 'index'])->name('telegram-crawler.index');
    Route::get('/groups', [\App\Http\Controllers\TelegramCrawlerController::class, 'groupsIndex'])->name('groups.index');
    Route::get('/telegram-groups', function () {
        return redirect()->route('groups.index', request()->query(), 301);
    })->name('telegram-groups.index');
    Route::patch('/groups/{group}', [\App\Http\Controllers\TelegramCrawlerController::class, 'groupsUpdate'])->name('groups.update');
    Route::patch('/telegram-groups/{group}', [\App\Http\Controllers\TelegramCrawlerController::class, 'groupsUpdate'])->name('telegram-groups.update');
    Route::get('/telegram-crawler/groups', [\App\Http\Controllers\TelegramCrawlerController::class, 'groups'])->name('telegram-crawler.groups');
    Route::post('/telegram-crawler/crawl', [\App\Http\Controllers\TelegramCrawlerController::class, 'crawl'])->name('telegram-crawler.crawl');
    Route::get('/telegram-crawler/crawl-status/{crawlId}', [\App\Http\Controllers\TelegramCrawlerController::class, 'crawlStatus'])->name('telegram-crawler.crawl-status');
    Route::post('/telegram-crawler/send-to-groups', [\App\Http\Controllers\TelegramCrawlerController::class, 'sendToGroups'])->name('telegram-crawler.send-to-groups');
    Route::get('/telegram-crawler/send-status/{sendId}', [\App\Http\Controllers\TelegramCrawlerController::class, 'sendToGroupsStatus'])->name('telegram-crawler.send-status');
    Route::post('/telegram-crawler/forward-to-groups', [\App\Http\Controllers\TelegramCrawlerController::class, 'forwardToGroups'])->name('telegram-crawler.forward-to-groups');
    Route::get('/telegram-crawler/forward-status/{forwardId}', [\App\Http\Controllers\TelegramCrawlerController::class, 'forwardToGroupsStatus'])->name('telegram-crawler.forward-status');
    Route::post('/telegram-crawler/sync-contacts', [\App\Http\Controllers\TelegramCrawlerController::class, 'syncContacts'])->name('telegram-crawler.sync-contacts');
    Route::get('/telegram-crawler/sync-status/{syncId}', [\App\Http\Controllers\TelegramCrawlerController::class, 'syncContactsStatus'])->name('telegram-crawler.sync-status');
    Route::get('/telegram-crawler/queue-status', [\App\Http\Controllers\TelegramCrawlerController::class, 'queueStatus'])->name('telegram-crawler.queue-status');
    Route::get('/telegram/scheduled-sends', [\App\Http\Controllers\TelegramScheduledSendController::class, 'index'])->name('telegram.scheduled-sends.index');
    Route::post('/telegram/scheduled-sends', [\App\Http\Controllers\TelegramScheduledSendController::class, 'store'])->name('telegram.scheduled-sends.store');
    Route::put('/telegram/scheduled-sends/{schedule}', [\App\Http\Controllers\TelegramScheduledSendController::class, 'update'])->name('telegram.scheduled-sends.update');
    Route::get('/telegram/scheduled-sends/{schedule}/report', [\App\Http\Controllers\TelegramScheduledSendController::class, 'report'])->name('telegram.scheduled-sends.report');
    Route::post('/telegram/scheduled-sends/{schedule}/stop', [\App\Http\Controllers\TelegramScheduledSendController::class, 'stop'])->name('telegram.scheduled-sends.stop');

    // Inbox (WhatsApp Messages)
    Route::get('/inbox', [\App\Http\Controllers\InboxController::class, 'index'])->name('inbox.index');
    Route::post('/inbox/send', [\App\Http\Controllers\InboxController::class, 'sendMessage'])->name('inbox.send');
    Route::post('/inbox/create-customer', [\App\Http\Controllers\InboxController::class, 'createCustomer'])->name('inbox.create-customer');
    Route::post('/inbox/assign-customer', [\App\Http\Controllers\InboxController::class, 'assignToCustomer'])->name('inbox.assign-customer');
    Route::get('/inbox/customers-for-assign', [\App\Http\Controllers\InboxController::class, 'customersForAssign'])->name('inbox.customers-for-assign');

    // Media (مدیریت فایل‌ها و پوشه‌ها)
    Route::get('/media', [\App\Http\Controllers\MediaController::class, 'index'])->name('media.index');
    Route::get('/media/list', [\App\Http\Controllers\MediaController::class, 'list'])->name('media.list');
    Route::post('/media/folders', [\App\Http\Controllers\MediaController::class, 'storeFolder'])->name('media.folders.store');
    Route::put('/media/folders/{folder}', [\App\Http\Controllers\MediaController::class, 'updateFolder'])->name('media.folders.update');
    Route::delete('/media/folders/{folder}', [\App\Http\Controllers\MediaController::class, 'destroyFolder'])->name('media.folders.destroy');
    Route::post('/media/files', [\App\Http\Controllers\MediaController::class, 'storeFile'])->name('media.files.store');
    Route::delete('/media/files/{mediaFile}', [\App\Http\Controllers\MediaController::class, 'destroyFile'])->name('media.files.destroy');

    // Scrap Tasks (Web Scraping)
    Route::get('/scrap-tasks', [\App\Http\Controllers\ScrapTaskController::class, 'index'])->name('scrap-tasks.index');
    Route::get('/scrap-tasks/create', [\App\Http\Controllers\ScrapTaskController::class, 'create'])->name('scrap-tasks.create');
    Route::post('/scrap-tasks', [\App\Http\Controllers\ScrapTaskController::class, 'store'])->name('scrap-tasks.store');
    Route::get('/scrap-tasks/{scrapTask}/edit', [\App\Http\Controllers\ScrapTaskController::class, 'edit'])->name('scrap-tasks.edit');
    Route::put('/scrap-tasks/{scrapTask}', [\App\Http\Controllers\ScrapTaskController::class, 'update'])->name('scrap-tasks.update');
    Route::get('/scrap-tasks/{scrapTask}', [\App\Http\Controllers\ScrapTaskController::class, 'show'])->name('scrap-tasks.show');
    Route::delete('/scrap-tasks/{scrapTask}', [\App\Http\Controllers\ScrapTaskController::class, 'destroy'])->name('scrap-tasks.destroy');
    Route::get('/scrap-tasks/{scrapTask}/result-urls', [\App\Http\Controllers\ScrapTaskController::class, 'resultUrls'])->name('scrap-tasks.result-urls');
    Route::post('/scrap-tasks/{scrapTask}/run', [\App\Http\Controllers\ScrapTaskController::class, 'run'])->name('scrap-tasks.run');
    Route::post('/scrap-tasks/{scrapTask}/run-sync', [\App\Http\Controllers\ScrapTaskController::class, 'runSync'])->name('scrap-tasks.run-sync');
    Route::post('/scrap-tasks/{scrapTask}/reset', [\App\Http\Controllers\ScrapTaskController::class, 'reset'])->name('scrap-tasks.reset');
    Route::get('/scrap-tasks/{scrapTask}/test-list-selector', [\App\Http\Controllers\ScrapTaskController::class, 'testListSelector'])->name('scrap-tasks.test-list-selector');
    Route::get('/scrap-tasks/{scrapTask}/run-status', [\App\Http\Controllers\ScrapTaskController::class, 'runStatus'])->name('scrap-tasks.run-status');
    Route::get('/scrap-tasks/{scrapTask}/export-excel', [\App\Http\Controllers\ScrapTaskController::class, 'exportExcel'])->name('scrap-tasks.export-excel');

    // Reports
    Route::get('/reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');

    // Settings
    Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/smtp', [\App\Http\Controllers\SettingsController::class, 'updateSmtp'])->name('settings.smtp.update');
    Route::post('/settings/ronibot', [\App\Http\Controllers\SettingsController::class, 'updateRonibot'])->name('settings.ronibot.update');
    Route::post('/settings/smtp/test', [\App\Http\Controllers\SettingsController::class, 'testSmtp'])->name('settings.smtp.test');
    Route::post('/settings/ronibot/test', [\App\Http\Controllers\SettingsController::class, 'testRonibot'])->name('settings.ronibot.test');
    Route::post('/settings/ronibot/partner/register', [\App\Http\Controllers\Settings\RonibotPartnerController::class, 'register'])->name('settings.ronibot.partner.register');
    Route::post('/settings/ronibot/partner/device', [\App\Http\Controllers\Settings\RonibotPartnerController::class, 'createDevice'])->name('settings.ronibot.partner.device');
    Route::post('/settings/ronibot/partner/qr', [\App\Http\Controllers\Settings\RonibotPartnerController::class, 'qr'])->name('settings.ronibot.partner.qr');
    Route::post('/settings/ronibot/partner/status', [\App\Http\Controllers\Settings\RonibotPartnerController::class, 'status'])->name('settings.ronibot.partner.status');
    Route::post('/settings/ronibot/partner/app', [\App\Http\Controllers\Settings\RonibotPartnerController::class, 'createApp'])->name('settings.ronibot.partner.app');
    Route::post('/settings/telegram', [\App\Http\Controllers\SettingsController::class, 'updateTelegram'])->name('settings.telegram.update');
    Route::post('/settings/telegram/test', [\App\Http\Controllers\SettingsController::class, 'testTelegram'])->name('settings.telegram.test');
    Route::post('/settings/telegram/register-webhook', [\App\Http\Controllers\SettingsController::class, 'registerTelegramWebhook'])->name('settings.telegram.register-webhook');
    Route::post('/settings/instagram', [\App\Http\Controllers\SettingsController::class, 'updateInstagram'])->name('settings.instagram.update');
    Route::get('/settings/instagram/connect', [\App\Http\Controllers\Settings\InstagramConnectionController::class, 'connect'])->name('settings.instagram.connect');
    Route::get('/settings/instagram/callback', [\App\Http\Controllers\Settings\InstagramConnectionController::class, 'callback'])->name('settings.instagram.callback');
    Route::post('/settings/instagram/disconnect', [\App\Http\Controllers\Settings\InstagramConnectionController::class, 'disconnect'])->name('settings.instagram.disconnect');
    Route::get('/settings/tiktok/connect', [\App\Http\Controllers\Settings\TikTokConnectionController::class, 'connect'])->name('settings.tiktok.connect');
    Route::get('/settings/tiktok/callback', [\App\Http\Controllers\Settings\TikTokConnectionController::class, 'callback'])->name('settings.tiktok.callback');
    Route::post('/settings/tiktok/disconnect', [\App\Http\Controllers\Settings\TikTokConnectionController::class, 'disconnect'])->name('settings.tiktok.disconnect');

    // Google Contacts (CRM → Google, one-way)
    Route::get('/settings/google-contacts/connect', [\App\Http\Controllers\Settings\GoogleContactsController::class, 'connect'])->name('settings.google-contacts.connect');
    Route::get('/settings/google-contacts/callback', [\App\Http\Controllers\Settings\GoogleContactsController::class, 'callback'])->name('settings.google-contacts.callback');
    Route::post('/settings/google-contacts/disconnect', [\App\Http\Controllers\Settings\GoogleContactsController::class, 'disconnect'])->name('settings.google-contacts.disconnect');
    Route::post('/settings/google-contacts/sync-start', [\App\Http\Controllers\Settings\GoogleContactsController::class, 'startBulkSync'])->name('settings.google-contacts.sync-start');
    Route::post('/settings/google-contacts/sync-stop', [\App\Http\Controllers\Settings\GoogleContactsController::class, 'requestStopBulkSync'])->name('settings.google-contacts.sync-stop');
    Route::get('/settings/google-contacts/sync-progress', [\App\Http\Controllers\Settings\GoogleContactsController::class, 'syncProgress'])->name('settings.google-contacts.sync-progress');
    Route::post('/settings/google-contacts/import-start', [\App\Http\Controllers\Settings\GoogleContactsController::class, 'startImportBulk'])->name('settings.google-contacts.import-start');
    Route::get('/settings/google-contacts/import-progress', [\App\Http\Controllers\Settings\GoogleContactsController::class, 'importProgress'])->name('settings.google-contacts.import-progress');
    Route::post('/settings/google-contacts/photo-sync-start', [\App\Http\Controllers\Settings\GoogleContactsController::class, 'startPhotoBulk'])->name('settings.google-contacts.photo-sync-start');
    Route::get('/settings/google-contacts/photo-sync-progress', [\App\Http\Controllers\Settings\GoogleContactsController::class, 'photoProgress'])->name('settings.google-contacts.photo-sync-progress');
    Route::post('/settings/google-contacts/photo-sync-stop', [\App\Http\Controllers\Settings\GoogleContactsController::class, 'requestStopPhotoBulk'])->name('settings.google-contacts.photo-sync-stop');
    Route::get('/settings/telegram/qr-code', [\App\Http\Controllers\Settings\TelegramConnectionController::class, 'qrCode'])->name('settings.telegram.qr-code');
    Route::get('/settings/telegram/status', [\App\Http\Controllers\Settings\TelegramConnectionController::class, 'status'])->name('settings.telegram.status');
    Route::post('/settings/telegram/start-phone-login', [\App\Http\Controllers\Settings\TelegramConnectionController::class, 'startPhoneLogin'])->name('settings.telegram.start-phone-login');
    Route::post('/settings/telegram/complete-phone-login', [\App\Http\Controllers\Settings\TelegramConnectionController::class, 'completePhoneLogin'])->name('settings.telegram.complete-phone-login');
    Route::post('/settings/telegram/complete-2fa', [\App\Http\Controllers\Settings\TelegramConnectionController::class, 'complete2fa'])->name('settings.telegram.complete-2fa');
    Route::post('/settings/telegram/disconnect', [\App\Http\Controllers\Settings\TelegramConnectionController::class, 'disconnect'])->name('settings.telegram.disconnect');
    Route::post('/settings/telegram/reset-session', [\App\Http\Controllers\Settings\TelegramConnectionController::class, 'resetSession'])->name('settings.telegram.reset-session');
    Route::post('/settings/instagram/revalidate', [\App\Http\Controllers\SettingsController::class, 'revalidateInstagramToken'])->name('settings.instagram.revalidate');
    Route::post('/settings/subscription/renew', [\App\Http\Controllers\SettingsController::class, 'renewSubscription'])->name('settings.subscription.renew');
    Route::post('/settings/organization-profile', [\App\Http\Controllers\OrganizationProfileController::class, 'update'])->name('settings.organization-profile.update');

    // Users Management (Admin Only)
    Route::get('/settings/users', [\App\Http\Controllers\UserController::class, 'index'])->name('settings.users.index');
    Route::post('/settings/users', [\App\Http\Controllers\UserController::class, 'store'])->name('settings.users.store');
    Route::put('/settings/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])->name('settings.users.update');
    Route::delete('/settings/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('settings.users.destroy');
    Route::post('/settings/organizations', [\App\Http\Controllers\OrganizationController::class, 'store'])->name('settings.organizations.store');
    Route::put('/settings/organizations/{organization}', [\App\Http\Controllers\OrganizationController::class, 'update'])->name('settings.organizations.update');
    Route::delete('/settings/organizations/{organization}', [\App\Http\Controllers\OrganizationController::class, 'destroy'])->name('settings.organizations.destroy');
    Route::post('/settings/organizations/{organization}/members', [\App\Http\Controllers\OrganizationController::class, 'addMember'])->name('settings.organizations.members.store');
    Route::put('/settings/organizations/{organization}/members/{user}', [\App\Http\Controllers\OrganizationController::class, 'updateMember'])->name('settings.organizations.members.update');
    Route::delete('/settings/organizations/{organization}/members/{user}', [\App\Http\Controllers\OrganizationController::class, 'removeMember'])->name('settings.organizations.members.destroy');

    Route::get('/settings/social-media-types', [\App\Http\Controllers\SocialMediaTypeController::class, 'index'])->name('settings.social-media-types');
    Route::post('/settings/social-media-types', [\App\Http\Controllers\SocialMediaTypeController::class, 'store'])->name('settings.social-media-types.store');
    Route::put('/settings/social-media-types/{socialMediaType}', [\App\Http\Controllers\SocialMediaTypeController::class, 'update'])->name('settings.social-media-types.update');
    Route::delete('/settings/social-media-types/{socialMediaType}', [\App\Http\Controllers\SocialMediaTypeController::class, 'destroy'])->name('settings.social-media-types.destroy');

    // Languages (Settings tab + shared)
    Route::get('/settings/languages', [\App\Http\Controllers\LanguageController::class, 'index'])->name('settings.languages.index');
    Route::post('/settings/languages', [\App\Http\Controllers\LanguageController::class, 'store'])->name('settings.languages.store');
    Route::put('/settings/languages/{language}', [\App\Http\Controllers\LanguageController::class, 'update'])->name('settings.languages.update');
    Route::delete('/settings/languages/{language}', [\App\Http\Controllers\LanguageController::class, 'destroy'])->name('settings.languages.destroy');

    // SuperAdmin (system-wide)
    Route::get('/superadmin/translations', [\App\Http\Controllers\SuperAdmin\TranslationsController::class, 'index'])->name('superadmin.translations.index');
    Route::get('/superadmin/languages', [\App\Http\Controllers\SuperAdmin\LanguagesPageController::class, 'index'])->name('superadmin.languages.index');
    Route::get('/superadmin/social-media-platforms', [\App\Http\Controllers\SuperAdmin\SocialMediaPlatformsController::class, 'index'])->name('superadmin.social-media-platforms.index');
    Route::get('/superadmin/organizations', [\App\Http\Controllers\SuperAdmin\OrganizationsPageController::class, 'index'])->name('superadmin.organizations.index');
    Route::post('/superadmin/translations/keys', [\App\Http\Controllers\SuperAdmin\TranslationsController::class, 'storeKey'])->name('superadmin.translations.keys.store');
    Route::put('/superadmin/translations/keys/{translationKey}', [\App\Http\Controllers\SuperAdmin\TranslationsController::class, 'updateKey'])->name('superadmin.translations.keys.update');
    Route::delete('/superadmin/translations/keys/{translationKey}', [\App\Http\Controllers\SuperAdmin\TranslationsController::class, 'destroyKey'])->name('superadmin.translations.keys.destroy');
    Route::get('/superadmin/translations/keys/{translationKey}/values', [\App\Http\Controllers\SuperAdmin\TranslationsController::class, 'valuesForKey'])->name('superadmin.translations.keys.values');
    Route::post('/superadmin/translations/values', [\App\Http\Controllers\SuperAdmin\TranslationsController::class, 'upsertValue'])->name('superadmin.translations.values.upsert');
    Route::post('/superadmin/translations/build-json', [\App\Http\Controllers\SuperAdmin\TranslationsController::class, 'buildJson'])->name('superadmin.translations.build-json');
    Route::get('/superadmin/plans', [\App\Http\Controllers\SuperAdmin\PlansController::class, 'index'])->name('superadmin.plans.index');
    Route::post('/superadmin/plans', [\App\Http\Controllers\SuperAdmin\PlansController::class, 'store'])->name('superadmin.plans.store');
    Route::put('/superadmin/plans/{plan}', [\App\Http\Controllers\SuperAdmin\PlansController::class, 'update'])->name('superadmin.plans.update');
    Route::delete('/superadmin/plans/{plan}', [\App\Http\Controllers\SuperAdmin\PlansController::class, 'destroy'])->name('superadmin.plans.destroy');
    Route::get('/superadmin/platform-notifications', [\App\Http\Controllers\SuperAdmin\PlatformNotificationsController::class, 'index'])->name('superadmin.platform-notifications.index');
    Route::put('/superadmin/platform-notifications', [\App\Http\Controllers\SuperAdmin\PlatformNotificationsController::class, 'update'])->name('superadmin.platform-notifications.update');
    Route::get('/superadmin/subscriptions', [\App\Http\Controllers\SuperAdmin\OrganizationSubscriptionsController::class, 'index'])->name('superadmin.subscriptions.index');
    Route::put('/superadmin/subscriptions/organizations/{organization}', [\App\Http\Controllers\SuperAdmin\OrganizationSubscriptionsController::class, 'update'])->name('superadmin.subscriptions.organizations.update');
    Route::post('/superadmin/subscriptions/organizations/{organization}/payments', [\App\Http\Controllers\SuperAdmin\OrganizationSubscriptionsController::class, 'addPayment'])->name('superadmin.subscriptions.organizations.payments.store');

    // Telegram Group Categories (Settings > Telegram tab)
    Route::get('/settings/telegram-group-categories', [\App\Http\Controllers\TelegramGroupCategoryController::class, 'index'])->name('settings.telegram-group-categories.index');
    Route::post('/settings/telegram-group-categories', [\App\Http\Controllers\TelegramGroupCategoryController::class, 'store'])->name('settings.telegram-group-categories.store');
    Route::put('/settings/telegram-group-categories/{telegramGroupCategory}', [\App\Http\Controllers\TelegramGroupCategoryController::class, 'update'])->name('settings.telegram-group-categories.update');
    Route::delete('/settings/telegram-group-categories/{telegramGroupCategory}', [\App\Http\Controllers\TelegramGroupCategoryController::class, 'destroy'])->name('settings.telegram-group-categories.destroy');
});
