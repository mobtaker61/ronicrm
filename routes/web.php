<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Public customer card
Route::get('/c/{shareKey}', [\App\Http\Controllers\PublicCustomerCardController::class, 'show'])->name('public.customer.card');
Route::post('/c/{shareKey}/share-via-whatsapp', [\App\Http\Controllers\PublicCustomerCardController::class, 'shareViaWhatsApp'])->name('public.customer.share-via-whatsapp');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Industries
    Route::resource('industries', \App\Http\Controllers\IndustryController::class)->except(['show', 'create', 'edit']);

    // Customers
    Route::resource('customers', \App\Http\Controllers\CustomerController::class);
    Route::patch('customers/{customer}/quick-update', [\App\Http\Controllers\CustomerController::class, 'quickUpdate'])->name('customers.quick-update');
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

    // Inbox (WhatsApp Messages)
    Route::get('/inbox', [\App\Http\Controllers\InboxController::class, 'index'])->name('inbox.index');
    Route::post('/inbox/send', [\App\Http\Controllers\InboxController::class, 'sendMessage'])->name('inbox.send');
    Route::post('/inbox/create-customer', [\App\Http\Controllers\InboxController::class, 'createCustomer'])->name('inbox.create-customer');

    // Reports
    Route::get('/reports', [\App\Http\Controllers\ReportController::class, 'index'])->name('reports.index');

    // Settings
    Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/smtp', [\App\Http\Controllers\SettingsController::class, 'updateSmtp'])->name('settings.smtp.update');
    Route::post('/settings/ronibot', [\App\Http\Controllers\SettingsController::class, 'updateRonibot'])->name('settings.ronibot.update');
    Route::post('/settings/smtp/test', [\App\Http\Controllers\SettingsController::class, 'testSmtp'])->name('settings.smtp.test');
    Route::post('/settings/ronibot/test', [\App\Http\Controllers\SettingsController::class, 'testRonibot'])->name('settings.ronibot.test');
    
    // Users Management (Admin Only)
    Route::get('/settings/users', [\App\Http\Controllers\UserController::class, 'index'])->name('settings.users.index');
    Route::post('/settings/users', [\App\Http\Controllers\UserController::class, 'store'])->name('settings.users.store');
    Route::put('/settings/users/{user}', [\App\Http\Controllers\UserController::class, 'update'])->name('settings.users.update');
    Route::delete('/settings/users/{user}', [\App\Http\Controllers\UserController::class, 'destroy'])->name('settings.users.destroy');
    
    // Ronibot Webhook (public route, no auth required)
    Route::post('/wpwebhook', [\App\Http\Controllers\RonibotWebhookController::class, 'handle'])->name('ronibot.webhook');
    Route::get('/settings/social-media-types', [\App\Http\Controllers\SocialMediaTypeController::class, 'index'])->name('settings.social-media-types');
    Route::post('/settings/social-media-types', [\App\Http\Controllers\SocialMediaTypeController::class, 'store'])->name('settings.social-media-types.store');
    Route::put('/settings/social-media-types/{socialMediaType}', [\App\Http\Controllers\SocialMediaTypeController::class, 'update'])->name('settings.social-media-types.update');
    Route::delete('/settings/social-media-types/{socialMediaType}', [\App\Http\Controllers\SocialMediaTypeController::class, 'destroy'])->name('settings.social-media-types.destroy');
});
