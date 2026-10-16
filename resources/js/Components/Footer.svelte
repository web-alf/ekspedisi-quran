<script>
  import { getImageUrl } from '../utils/formHelpers.js';
  import HeroIcon from '@/Components/UI/HeroIcon.svelte';
  export let settings = {};
</script>

<footer class="bg-gray-900 text-white py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-8">
            <!-- Logo & Description -->
            <div class="md:col-span-2">
                <div class="flex items-center">
                    <img src={getImageUrl(settings.admin_logo, "/images/logo-ekspedisiquran-putih.webp")} alt="Logo Ekspedisi Qur'an" class="w-48 h-24 object-contain" />
                </div>
                <p class="text-gray-400 mb-6 leading-relaxed">
                    {settings.landing_description || 'Program distribusi mushaf Al-Quran ke seluruh pelosok Indonesia. Menyebarkan cahaya kebaikan melalui kitab suci kepada mereka yang membutuhkan.'}
                </p>


                <!-- Social Media Links -->
                {#if settings.social_facebook || settings.social_instagram || settings.social_twitter || settings.social_youtube}
                <div class="flex space-x-4">
                    {#if settings.social_facebook}
                    <a href={settings.social_facebook} target="_blank" class="text-gray-400 hover:text-white transition-colors">
                        <HeroIcon name="facebook" class="w-6 h-6" />
                    </a>
                    {/if}
                    
                    {#if settings.social_instagram}
                    <a href={settings.social_instagram} target="_blank" class="text-gray-400 hover:text-white transition-colors">
                        <HeroIcon name="instagram" class="w-6 h-6" />
                    </a>
                    {/if}
                    
                    {#if settings.social_twitter}
                    <a href={settings.social_twitter} target="_blank" class="text-gray-400 hover:text-white transition-colors">
                        <HeroIcon name="twitter" class="w-6 h-6" />
                    </a>
                    {/if}
                    
                    {#if settings.social_youtube}
                    <a href={settings.social_youtube} target="_blank" class="text-gray-400 hover:text-white transition-colors">
                        <HeroIcon name="youtube" class="w-6 h-6" />
                    </a>
                    {/if}
                </div>
                {/if}
            </div>
            
            <!-- Legalitas -->
            <div class="md:col-span-2">
                <h3 class="text-lg font-semibold mb-6">Legalitas</h3>
                <div class="space-y-3 text-sm">
                    <div class="text-gray-400 leading-relaxed">
                        <p class="font-medium text-gray-300 mb-1">Keputusan Menteri Hukum Dan Hak Asasi Manusia Republik Indonesia</p>
                        <p>Nomor AHU-0009981.AH.01.07 Tahun 2017</p>
                    </div>
                    <div class="text-gray-400">
                        <p><span class="font-medium text-gray-300">NPWP:</span> 82.204.833.6-503.000</p>
                    </div>
                    <div class="text-gray-400">
                        <p><span class="font-medium text-gray-300">Izin BWI:</span> 3.3.00444</p>
                    </div>
                    <div class="text-gray-400">
                        <p><span class="font-medium text-gray-300">Izin PUB:</span> B/4745/460/VII/2024</p>
                    </div>
                </div>
            </div>
            
            <!-- Contact Info -->
            <div>
                <h3 class="text-lg font-semibold mb-6">Kontak</h3>
                <ul class="space-y-4">
                    <li class="flex items-center">
                        <HeroIcon name="envelope" class="flex-shrink-0 w-5 h-5 text-[#eb3434] mr-3" />
                        <span class="text-gray-400 text-sm break-all">{settings.contact_email || 'info@ekspedisiquran.com'}</span>
                    </li>
                    <li class="flex items-center">
                        <HeroIcon name="phone" class="flex-shrink-0 w-5 h-5 text-[#eb3434] mr-3" />
                        <span class="text-gray-400 text-sm">{settings.contact_phone || '+62 21 1234 5678'}</span>
                    </li>
                    <li class="flex items-start">
                        <HeroIcon name="map-pin" class="flex-shrink-0 w-5 h-5 text-[#eb3434] mr-3 mt-1" />
                        <span class="text-gray-400 text-sm">{settings.contact_address || 'Semarang, Indonesia'}</span>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Bottom Footer -->
        <div class="border-t border-gray-800 mt-12 pt-8 flex flex-col md:flex-row justify-between items-center">
            <p class="text-gray-400 text-sm text-center md:text-left">
                © 2025 {settings.app_name || 'Ekspedisi Qur\'an'}. All rights reserved. <br class="md:hidden">Crafted with ❤️ by <a href="https://aguss.id" target="_blank" class="text-[#eb3434] hover:text-red-500 font-medium transition-colors">Agus</a>
            </p>
            <div class="flex space-x-6 mt-4 md:mt-0">
                <a href="/privacy-policy" class="text-gray-400 hover:text-white text-sm transition-colors duration-300">Privacy Policy</a>
                <a href="/terms-of-service" class="text-gray-400 hover:text-white text-sm transition-colors duration-300">Terms of Service</a>
            </div>
        </div>
    </div>
</footer> 