<script>
  import { router } from '@inertiajs/svelte';
  import { fade, scale } from 'svelte/transition';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import FlashMessage from '../../../Components/FlashMessage.svelte';
  
  // Props from backend
  export const auth = {};
  export let stats = {};
  export let recent_tracks = [];
  export const errors = {};
  export const flash = {};
  
  let trackingCode = '';
  let trackingResult = null;
  let isSearching = false;
  
  function handleTrackingSearch() {
    if (!trackingCode.trim()) return;
    
    isSearching = true;
    
    // Simulate API call - in real implementation, this would be a proper API call
    setTimeout(() => {
      // Mock tracking result
      trackingResult = {
        code: trackingCode.trim(),
        status: 'shipped',
        wakif_name: 'Ahmad Fauzi',
        destination: 'Masjid Al-Hikmah, Lombok Timur',
        tracking_history: [
          {
            status: 'created',
            description: 'QR Code dibuat',
            location: 'Warehouse Jakarta',
            timestamp: '2025-01-15 08:00:00'
          },
          {
            status: 'packed',
            description: 'Al-Quran dikemas',
            location: 'Warehouse Jakarta',
            timestamp: '2025-01-15 10:30:00'
          },
          {
            status: 'shipped',
            description: 'Dalam perjalanan',
            location: 'Hub Surabaya',
            timestamp: '2025-01-16 14:20:00'
          }
        ]
      };
      isSearching = false;
    }, 1500);
  }
  
  function resetSearch() {
    trackingCode = '';
    trackingResult = null;
  }
  
  function openPublicPage() {
    window.open('/tracking', '_blank');
  }
  
  function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('id-ID', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }
  
  function getStatusColor(status) {
    switch(status) {
      case 'created': return 'bg-gray-100 text-gray-800';
      case 'packed': return 'bg-blue-100 text-blue-800';
      case 'shipped': return 'bg-yellow-100 text-yellow-800';
      case 'delivered': return 'bg-green-100 text-green-800';
      case 'cancelled': return 'bg-red-100 text-red-800';
      default: return 'bg-gray-100 text-gray-800';
    }
  }
  
  function getStatusText(status) {
    switch(status) {
      case 'created': return 'Dibuat';
      case 'packed': return 'Dikemas';
      case 'shipped': return 'Dikirim';
      case 'delivered': return 'Terkirim';
      case 'cancelled': return 'Dibatalkan';
      default: return 'Unknown';
    }
  }
  
  function getStatusIcon(status) {
    switch(status) {
      case 'created': return '📋';
      case 'packed': return '📦';
      case 'shipped': return '🚚';
      case 'delivered': return '✅';
      case 'cancelled': return '❌';
      default: return '❓';
    }
  }
</script>

<svelte:head>
  <title>Public Tracking - Ekspedisi Qur'an</title>
</svelte:head>

<AdminLayout>
  <div class="p-6">
    <!-- Flash Messages -->
    <FlashMessage />
    
    <!-- Header -->
    <div class="mb-6">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <button 
            on:click={() => router.visit('/admin/dashboard')}
            class="mr-4 p-2 text-gray-600 hover:text-gray-900 transition-colors"
          >
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
          </button>
          <div>
            <h1 class="text-lg font-bold text-gray-900">Public Tracking</h1>
            <p class="text-sm text-gray-500">Sistem tracking untuk masyarakat umum</p>
          </div>
        </div>
        
        <div class="flex items-center space-x-3">
          <button 
            on:click={openPublicPage}
            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2M7 7l10 10M17 7v4m0 0H13"/>
            </svg>
            Buka Halaman Public
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      
      <!-- Tracking Tool -->
      <div class="lg:col-span-1">
        
        <!-- Tracking Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
          <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Lacak Pengiriman</h3>
            <p class="text-sm text-gray-500">Masukkan kode tracking</p>
          </div>
          
          <div class="p-6">
            <form on:submit|preventDefault={handleTrackingSearch} class="space-y-4">
              <input
                type="text"
                bind:value={trackingCode}
                placeholder="Masukkan kode tracking..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                required
              />
              
              <div class="flex space-x-2">
                <button
                  type="submit"
                  disabled={isSearching}
                  class="flex-1 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 text-white px-4 py-2 rounded-lg font-medium transition-colors flex items-center justify-center"
                >
                  {#if isSearching}
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>    
                    </svg>
                    Mencari...
                  {:else}
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Lacak
                  {/if}
                </button>
                
                {#if trackingResult}
                  <button
                    type="button"
                    on:click={resetSearch}
                    class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-medium transition-colors"
                  >
                    Reset
                  </button>
                {/if}
              </div>
            </form>
          </div>
        </div>

        <!-- Statistics -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
          <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Statistik Tracking</h3>
          </div>
          <div class="p-6">
            <div class="grid grid-cols-2 gap-4">
              <div class="text-center">
                <p class="text-2xl font-bold text-purple-600">{stats.total_tracks || 0}</p>
                <p class="text-sm text-gray-500">Total Tracking</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-bold text-blue-600">{stats.today_tracks || 0}</p>
                <p class="text-sm text-gray-500">Hari Ini</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-bold text-green-600">{stats.delivered || 0}</p>
                <p class="text-sm text-gray-500">Terkirim</p>
              </div>
              <div class="text-center">
                <p class="text-2xl font-bold text-orange-600">{stats.in_transit || 0}</p>
                <p class="text-sm text-gray-500">Perjalanan</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Tracking Results & History -->
      <div class="lg:col-span-2">
        
        {#if trackingResult}
          <!-- Tracking Result -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
              <div class="flex justify-between items-center">
                <div>
                  <h3 class="text-lg font-semibold text-gray-900">Hasil Tracking</h3>
                  <p class="text-sm text-gray-500">Kode: {trackingResult.code}</p>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {getStatusColor(trackingResult.status)}">
                  {getStatusText(trackingResult.status)}
                </span>
              </div>
            </div>
            
            <div class="p-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <h4 class="text-sm font-medium text-gray-900 mb-2">Informasi Wakif</h4>
                  <p class="text-sm text-gray-600">{trackingResult.wakif_name}</p>
                </div>
                <div>
                  <h4 class="text-sm font-medium text-gray-900 mb-2">Tujuan Pengiriman</h4>
                  <p class="text-sm text-gray-600">{trackingResult.destination}</p>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Tracking History -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">Riwayat Pengiriman</h3>
              <p class="text-sm text-gray-500">Timeline perjalanan Al-Quran</p>
            </div>
            
            <div class="p-6">
              <div class="flow-root">
                <ul class="-mb-8">
                  {#each trackingResult.tracking_history as history, index}
                    <li>
                      <div class="relative pb-8">
                        {#if index !== trackingResult.tracking_history.length - 1}
                          <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                        {/if}
                        <div class="relative flex space-x-3">
                          <div>
                            <span class="h-8 w-8 rounded-full {getStatusColor(history.status)} flex items-center justify-center ring-8 ring-white text-lg">
                              {getStatusIcon(history.status)}
                            </span>
                          </div>
                          <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                            <div>
                              <p class="text-sm font-medium text-gray-900">{history.description}</p>
                              <p class="text-sm text-gray-500">{history.location}</p>
                            </div>
                            <div class="text-right text-sm whitespace-nowrap text-gray-500">
                              {formatDate(history.timestamp)}
                            </div>
                          </div>
                        </div>
                      </div>
                    </li>
                  {/each}
                </ul>
              </div>
            </div>
          </div>
        {:else}
          <!-- Recent Tracking Activity -->
          <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
              <h3 class="text-lg font-semibold text-gray-900">Aktivitas Tracking Terbaru</h3>
              <p class="text-sm text-gray-500">Pencarian tracking yang dilakukan public</p>
            </div>
            
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kode</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  {#each recent_tracks as track}
                    <tr class="hover:bg-gray-50">
                      <!-- Code -->
                      <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                          <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                          </div>
                          <div>
                            <div class="text-sm font-medium text-gray-900">{track.tracking_code}</div>
                            <div class="text-sm text-gray-500">{track.wakif_name || 'Unknown'}</div>
                          </div>
                        </div>
                      </td>
                      
                      <!-- Status -->
                      <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {getStatusColor(track.status)}">
                          {getStatusText(track.status)}
                        </span>
                      </td>
                      
                      <!-- IP Address -->
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {track.ip_address}
                      </td>
                      
                      <!-- Time -->
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {formatDate(track.tracked_at)}
                      </td>
                    </tr>
                  {:else}
                    <tr>
                      <td colspan="4" class="px-6 py-12 text-center">
                        <div class="text-gray-500">
                          <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                          </svg>
                          <p class="text-lg font-medium">Belum ada aktivitas tracking</p>
                          <p class="text-sm">Aktivitas pencarian public akan muncul di sini</p>
                        </div>
                      </td>
                    </tr>
                  {/each}
                </tbody>
              </table>
            </div>
          </div>
        {/if}
      </div>
    </div>
  </div>
</AdminLayout>
