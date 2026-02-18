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
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Public customer card
Route::get('/c/{shareKey}', [\App\Http\Controllers\PublicCustomerCardController::class, 'show'])->name('public.customer.card');
Route::post('/c/{shareKey}/share-via-whatsapp', [\App\Http\Controllers\PublicCustomerCardController::class, 'shareViaWhatsApp'])->name('public.customer.share-via-whatsapp');

// Public project share (no auth)
Route::get('/p/{shareToken}', [\App\Http\Controllers\PublicProjectShareController::class, 'show'])->name('public.project.share');
Route::get('/p/{shareToken}/customer/{shareKey}', [\App\Http\Controllers\PublicProjectShareController::class, 'getCustomer'])->name('public.project.customer');
Route::get('/p/{shareToken}/export-excel', [\App\Http\Controllers\PublicProjectShareController::class, 'exportExcel'])->name('public.project.export-excel');

// Webhooks (no auth, no CSRF – called by Telegram, Ronibot, Meta)
Route::post('/wpwebhook', [\App\Http\Controllers\RonibotWebhookController::class, 'handle'])->name('ronibot.webhook');
Route::post('/telegram-webhook', [\App\Http\Controllers\TelegramWebhookController::class, 'handle'])->name('telegram.webhook');
Route::get('/instagram-webhook', [\App\Http\Controllers\InstagramWebhookController::class, 'verify'])->name('instagram.webhook.verify');
Route::post('/instagram-webhook', [\App\Http\Controllers\InstagramWebhookController::class, 'handle'])->middleware('throttle:120,1')->name('instagram.webhook');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

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
    Route::get('/telegram-groups', [\App\Http\Controllers\TelegramCrawlerController::class, 'groupsIndex'])->name('telegram-groups.index');
    Route::get('/telegram-crawler/groups', [\App\Http\Controllers\TelegramCrawlerController::class, 'groups'])->name('telegram-crawler.groups');
    Route::post('/telegram-crawler/crawl', [\App\Http\Controllers\TelegramCrawlerController::class, 'crawl'])->name('telegram-crawler.crawl');
    Route::get('/telegram-crawler/crawl-status/{crawlId}', [\App\Http\Controllers\TelegramCrawlerController::class, 'crawlStatus'])->name('telegram-crawler.crawl-status');
    Route::post('/telegram-crawler/send-to-groups', [\App\Http\Controllers\TelegramCrawlerController::class, 'sendToGroups'])->name('telegram-crawler.send-to-groups');
    Route::get('/telegram-crawler/send-status/{sendId}', [\App\Http\Controllers\TelegramCrawlerController::class, 'sendToGroupsStatus'])->name('telegram-crawler.send-status');
    Route::post('/telegram-crawler/sync-contacts', [\App\Http\Controllers\TelegramCrawlerController::class, 'syncContacts'])->name('telegram-crawler.sync-contacts');
    Route::get('/telegram-crawler/sync-status/{syncId}', [\App\Http\Controllers\TelegramCrawlerController::class, 'syncContactsStatus'])->name('telegram-crawler.sync-status');
    Route::get('/telegram-crawler/queue-status', [\App\Http\Controllers\TelegramCrawlerController::class, 'queueStatus'])->name('telegram-crawler.queue-status');

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
    Route::post('/settings/telegram', [\App\Http\Controllers\SettingsController::class, 'updateTelegram'])->name('settings.telegram.update');
    Route::post('/settings/telegram/test', [\App\Http\Controllers\SettingsController::class, 'testTelegram'])->name('settings.telegram.test');
    Route::post('/settings/instagram', [\App\Http\Controllers\SettingsController::class, 'updateInstagram'])->name('settings.instagram.update');
    Route::get('/settings/instagram/connect', [\App\Http\Controllers\Settings\InstagramConnectionController::class, 'connect'])->name('settings.instagram.connect');
    Route::get('/settings/instagram/callback', [\App\Http\Controllers\Settings\InstagramConnectionController::class, 'callback'])->name('settings.instagram.callback');
    Route::post('/settings/instagram/disconnect', [\App\Http\Controllers\Settings\InstagramConnectionController::class, 'disconnect'])->name('settings.instagram.disconnect');
    Route::get('/settings/telegram/qr-code', [\App\Http\Controllers\Settings\TelegramConnectionController::class, 'qrCode'])->name('settings.telegram.qr-code');
    Route::post('/settings/telegram/disconnect', [\App\Http\Controllers\Settings\TelegramConnectionController::class, 'disconnect'])->name('settings.telegram.disconnect');
    Route::post('/settings/telegram/reset-session', [\App\Http\Controllers\Settings\TelegramConnectionController::class, 'resetSession'])->name('settings.telegram.reset-session');
    Route::post('/settings/instagram/revalidate', [\App\Http\Controllers\SettingsController::class, 'revalidateInstagramToken'])->name('settings.instagram.revalidate');

    // Users Management (Admin Only)
    Route::get('/settings/users', [\App\Http\Controllers\UserController::class, 'index'])->name('settings.users.index');
    Route::post('/settings/users', [\App\Http\Controllers\UserController::class, 'store'])->name('settings.users.store');
    Route::put('/settings/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])->name('settings.users.update');
    Route::delete('/settings/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('settings.users.destroy');

    Route::get('/settings/social-media-types', [\App\Http\Controllers\SocialMediaTypeController::class, 'index'])->name('settings.social-media-types');
    Route::post('/settings/social-media-types', [\App\Http\Controllers\SocialMediaTypeController::class, 'store'])->name('settings.social-media-types.store');
    Route::put('/settings/social-media-types/{socialMediaType}', [\App\Http\Controllers\SocialMediaTypeController::class, 'update'])->name('settings.social-media-types.update');
    Route::delete('/settings/social-media-types/{socialMediaType}', [\App\Http\Controllers\SocialMediaTypeController::class, 'destroy'])->name('settings.social-media-types.destroy');
});
