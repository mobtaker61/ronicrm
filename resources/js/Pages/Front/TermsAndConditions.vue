<template>
    <FrontLayout app-name="RoniCRM">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
            <h1 class="text-3xl font-bold text-gray-900 mb-8">Terms and Conditions</h1>
            <p class="text-sm text-gray-500 mb-8">Last updated: February 2025</p>
            <div class="prose prose-gray max-w-none space-y-6 text-gray-600">
                <p class="leading-relaxed">
                    These Terms and Conditions (“Terms”) govern your access to and use of the RoniCRM service provided by Roni Plus (“we”, “us”, or “our”). By using RoniCRM, you agree to these Terms. If you do not agree, please do not use the service.
                </p>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">1. Acceptance of terms</h2>
                    <p class="leading-relaxed">
                        Access to and use of RoniCRM is conditional on your acceptance of these Terms. We may modify these Terms from time to time; we will notify you of material changes via the site or by email. Your continued use of the service after such notice constitutes acceptance of the updated Terms.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">2. Permitted use</h2>
                    <p class="leading-relaxed">
                        You agree to use RoniCRM only for lawful purposes and in compliance with applicable local and international laws. You may not use the service to send spam, distribute illegal or harmful content, or infringe the rights of others. You are responsible for ensuring that your use of messaging channels (e.g. WhatsApp, Telegram, Instagram) complies with each platform’s terms and policies.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">3. Account and security</h2>
                    <p class="leading-relaxed">
                        You are responsible for maintaining the confidentiality of your login credentials and for all activity that occurs under your account. You must notify us promptly of any unauthorized access or misuse of your account.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">4. Ownership and content</h2>
                    <p class="leading-relaxed">
                        The data and content you enter into RoniCRM remain yours. By using the service, you grant us the rights necessary to process, store, and display that data in order to provide RoniCRM. The RoniCRM software, design, and branding are owned by Roni Plus and are protected by copyright and other intellectual property laws.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">5. Limitation of liability</h2>
                    <p class="leading-relaxed">
                        RoniCRM is provided “as is”. To the maximum extent permitted by law, we disclaim all warranties (express or implied) regarding availability, accuracy, or fitness for a particular purpose. We are not liable for any direct, indirect, incidental, or consequential damages arising from your use or inability to use the service.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">6. Suspension and termination</h2>
                    <p class="leading-relaxed">
                        We may suspend or terminate your access to RoniCRM if you breach these Terms or for technical or security reasons. Where reasonably possible, we will notify you via the site or email. Upon termination, your right to use the service ceases; we may retain or delete data in accordance with our Privacy Policy and applicable law.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">7. Governing law</h2>
                    <p class="leading-relaxed">
                        These Terms are governed by the laws applicable to Roni Plus. Any dispute shall first be addressed through good-faith negotiation; if unresolved, it may be submitted to the competent courts.
                    </p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">8. Contact</h2>
                    <p class="leading-relaxed">
                        For questions about these Terms and Conditions, contact us at <a href="mailto:support@ronicrm.com" class="text-blue-600 hover:underline">support@ronicrm.com</a>. RoniCRM is operated by Roni Plus. You can also use the contact information provided on the RoniCRM website (e.g. ronicrm.com).
                    </p>
                </section>
            </div>
            <div class="mt-10 pt-6 border-t border-gray-200">
                <Link :href="route('front.welcome')" class="text-blue-600 hover:text-blue-700 font-medium">← Back to home</Link>
            </div>
        </div>
    </FrontLayout>
</template>

<script setup>
import FrontLayout from '@/Layouts/FrontLayout.vue';
import { Link } from '@inertiajs/vue3';
</script>
