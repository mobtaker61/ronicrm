<template>
    <FrontLayout app-name="RoniCRM">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
            <h1 class="text-3xl font-bold text-gray-900 mb-8">Privacy Policy</h1>
            <p class="text-sm text-gray-500 mb-8">Last updated: February 2025</p>
            <div class="prose prose-gray max-w-none space-y-6 text-gray-600">
                <p class="leading-relaxed">
                    RoniCRM ("we", "our", or "Roni Plus") operates the RoniCRM customer relationship management service. This Privacy Policy describes how we collect, use, and protect your information when you use our service. By using RoniCRM, you agree to this policy.
                </p>
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">1. Information we collect</h2>
                    <p class="leading-relaxed">
                        We may collect and store: (a) account information such as name, email address, and username; (b) customer and contact data you enter into RoniCRM (e.g. names, phone numbers, social handles); (c) usage data such as IP address, browser type, and actions within the service; and (d) session and authentication data necessary to operate the service securely.
                    </p>
                </section>
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">2. How we use your information</h2>
                    <p class="leading-relaxed">
                        We use the information we collect to provide, maintain, and improve RoniCRM; to communicate with you about the service (e.g. support, security notices); to comply with applicable law; and to protect the security and integrity of our systems. We do not sell your personal information to third parties. We may share data with service providers who assist in operating the service (e.g. hosting, email delivery), under strict confidentiality and data-processing terms.
                    </p>
                </section>
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">3. Data security</h2>
                    <p class="leading-relaxed">
                        We implement technical and organizational measures to protect your personal data against unauthorized access, alteration, or disclosure. Access to data is restricted to authorized personnel. Passwords are stored using industry-standard hashing. We recommend you use a strong password and keep your login credentials confidential.
                    </p>
                </section>
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">4. Cookies and similar technologies</h2>
                    <p class="leading-relaxed">
                        We may use cookies and similar technologies to enable the site to function correctly, to keep you logged in, and to improve your experience. By continuing to use the site, you consent to such use. You can adjust your browser settings to limit or block cookies if you prefer.
                    </p>
                </section>
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">5. Data retention</h2>
                    <p class="leading-relaxed">
                        We retain your data for as long as your account is active or as needed to provide the service and comply with legal obligations. If you close your account, we may retain certain information for a limited period for legal, security, or operational reasons.
                    </p>
                </section>
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">6. Changes to this policy</h2>
                    <p class="leading-relaxed">
                        We may update this Privacy Policy from time to time. Material changes will be communicated via the site or by email where appropriate. Continued use of the service after changes constitutes acceptance of the updated policy.
                    </p>
                </section>
                <section>
                    <h2 class="text-xl font-semibold text-gray-900 mt-8 mb-3">7. Contact us</h2>
                    <p class="leading-relaxed">
                        For questions about this Privacy Policy or your personal data, contact us at <a href="mailto:support@roniplus.ae" class="text-blue-600 hover:underline">support@roniplus.ae</a>. RoniCRM is provided by Roni Plus. You can also reach us through the contact details published on the RoniCRM website (e.g. crm.roniplus.ae).
                    </p>
                </section>
            </div>
            <div class="mt-10 pt-6 border-t border-gray-200">
                <Link :href="route('front.welcome')" class="text-blue-600 hover:text-blue-700 font-medium">Back to home</Link>
            </div>
        </div>
    </FrontLayout>
</template>

<script setup>
import FrontLayout from '@/Layouts/FrontLayout.vue';
import { Link } from '@inertiajs/vue3';
</script>
