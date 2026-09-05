<script>
  import { useForm } from '@inertiajs/svelte';
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '../../../Layouts/AdminLayout.svelte';
  import FlashMessage from '../../../Components/FlashMessage.svelte';
  import HeroIcon from '../../../Components/UI/HeroIcon.svelte';
  
  export let roles = {};
  export const auth = {};
  export const errors = {};
  export const flash = {};
  
  let form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
    is_active: true
  });
  
  function handleSubmit(e) {
    e.preventDefault();
    $form.post('/admin/users', {
      onSuccess: () => {
        // Form will be redirected by controller
      }
    });
  }
  
  function handleCancel() {
    router.visit('/admin/users');
  }
</script>

<svelte:head>
  <title>Tambah User - Ekspedisi Qur'an</title>
</svelte:head>

<AdminLayout>
  <div class="p-6">
    <!-- Header -->
    <div class="mb-6">
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <button 
            on:click={() => router.visit('/admin/users')}
            class="mr-4 p-2 text-gray-600 hover:text-gray-900 transition-colors"
          >
            <HeroIcon name="chevron-left" class="w-5 h-5" />
          </button>
          <div>
            <h1 class="text-2xl font-semibold text-gray-900">Tambah User Baru</h1>
            <p class="text-sm text-gray-500 mt-1">Buat akun pengguna baru untuk sistem</p>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Flash Messages -->
    <FlashMessage />

    <!-- Form Card -->
    <div class="bg-white rounded-lg shadow-sm p-6">
      <form on:submit={handleSubmit} class="space-y-6">
        
        <!-- Name Field -->
        <div>
          <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
            Nama Lengkap <span class="text-red-500">*</span>
          </label>
          <input
            id="name"
            type="text"
            bind:value={$form.name}
            required
            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] transition duration-200"
            class:border-red-500={$form.errors.name}
            placeholder="Masukkan nama lengkap"
          />
          {#if $form.errors.name}
            <p class="mt-2 text-sm text-red-600 flex items-center">
              <HeroIcon name="exclamation-circle" class="w-4 h-4 mr-1 text-red-600" />
              {$form.errors.name}
            </p>
          {/if}
        </div>

        <!-- Email Field -->
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
            Alamat Email <span class="text-red-500">*</span>
          </label>
          <input
            id="email"
            type="email"
            bind:value={$form.email}
            required
            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] transition duration-200"
            class:border-red-500={$form.errors.email}
            placeholder="contoh@ekspedisiquran.com"
          />
          {#if $form.errors.email}
            <p class="mt-2 text-sm text-red-600 flex items-center">
              <HeroIcon name="exclamation-circle" class="w-4 h-4 mr-1 text-red-600" />
              {$form.errors.email}
            </p>
          {/if}
        </div>

        <!-- Role Field -->
        <div>
          <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
            Role <span class="text-red-500">*</span>
          </label>
          <select
            id="role"
            bind:value={$form.role}
            required
            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] transition duration-200"
            class:border-red-500={$form.errors.role}
          >
            <option value="">Pilih Role</option>
            {#each Object.entries(roles) as [value, label]}
              <option {value}>{label}</option>
            {/each}
          </select>
          {#if $form.errors.role}
            <p class="mt-2 text-sm text-red-600 flex items-center">
              <HeroIcon name="exclamation-circle" class="w-4 h-4 mr-1 text-red-600" />
              {$form.errors.role}
            </p>
          {/if}
        </div>

        <!-- Password Field -->
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
            Kata Sandi <span class="text-red-500">*</span>
          </label>
          <input
            id="password"
            type="password"
            bind:value={$form.password}
            required
            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] transition duration-200"
            class:border-red-500={$form.errors.password}
            placeholder="Minimal 8 karakter"
          />
          {#if $form.errors.password}
            <p class="mt-2 text-sm text-red-600 flex items-center">
              <HeroIcon name="exclamation-circle" class="w-4 h-4 mr-1 text-red-600" />
              {$form.errors.password}
            </p>
          {/if}
        </div>

        <!-- Password Confirmation Field -->
        <div>
          <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
            Konfirmasi Kata Sandi <span class="text-red-500">*</span>
          </label>
          <input
            id="password_confirmation"
            type="password"
            bind:value={$form.password_confirmation}
            required
            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] transition duration-200"
            placeholder="Ulangi kata sandi"
          />
        </div>

        <!-- Status Field -->
        <div>
          <label class="flex items-center">
            <input
              type="checkbox"
              bind:checked={$form.is_active}
              class="h-4 w-4 text-[#eb3434] focus:ring-[#eb3434] border-gray-300 rounded"
            />
            <span class="ml-2 text-sm text-gray-700">User aktif</span>
          </label>
          <p class="mt-1 text-xs text-gray-500">User yang aktif dapat login ke sistem</p>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
          <button
            type="button"
            on:click={handleCancel}
            class="px-6 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:ring-offset-2 transition-colors"
          >
            Batal
          </button>
          <button
            type="submit"
            disabled={$form.processing}
            class="px-6 py-2 bg-[#eb3434] hover:bg-red-600 text-white rounded-lg text-sm font-medium focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center"
          >
            {#if $form.processing}
              <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Menyimpan...
            {:else}
              <HeroIcon name="check" class="w-4 h-4 mr-2" />
              Simpan User
            {/if}
          </button>
        </div>
      </form>
    </div>
  </div>
</AdminLayout>
