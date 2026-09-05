<script>
  import { useForm } from '@inertiajs/svelte'
  import PublicLayout from '@/Layouts/PublicLayout.svelte'
  import HeroIcon from '@/Components/UI/HeroIcon.svelte'
  
  // Props from controller
  export const errors = {};
  export const auth = {};
  export let flash = {};
  
  // Form data
  let form = useForm({
    email: '',
    password: '',
    remember: false
  })

  // Password visibility toggle
  let showPassword = false

  function togglePasswordVisibility() {
    showPassword = !showPassword
  }

  // Submit form
  function submit(e) {
    e.preventDefault()
    $form.post('/login', {
      onSuccess: () => {
        // Set flag to indicate user just logged in (for CSRF timing fix)
        sessionStorage.setItem('just_logged_in', 'true');
      },
      onFinish: () => {
        // Clear password on finish (success or error)
        $form.password = ''
      }
    })
  }
</script>

<svelte:head>
  <title>Login - Ekspedisi Qur'an</title>
  <meta name="description" content="Login ke sistem admin Ekspedisi Qur'an">
</svelte:head>

<PublicLayout>
  <div class="relative min-h-screen bg-gradient-to-br from-white to-red-50 flex items-center justify-center py-16 px-4 sm:px-6 lg:px-8 overflow-hidden">
    <!-- Islamic Pattern Background -->
    <div class="absolute inset-0 opacity-30 islamic-geometric"></div>
    
    <div class="relative z-10 max-w-md w-full space-y-8">
    
    <!-- Header -->
    <div class="text-center">
      <div class="mx-auto h-20 w-20 bg-[#eb3434] rounded-2xl flex items-center justify-center mb-6 shadow-lg text-white">
        <HeroIcon name="book-open" class="h-10 w-10" />
      </div>
      <h1 class="text-3xl font-bold text-gray-900 mb-2">Ekspedisi Qur'an</h1>
      <p class="text-gray-600">Masuk ke Panel Administrator</p>
    </div>

    <!-- Login Form -->
    <div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-white/50 p-8 relative">
      <!-- Decorative elements -->
      <div class="absolute -top-2 -left-2 w-4 h-4 bg-[#eb3434] rounded-full opacity-60"></div>
      <div class="absolute -top-2 -right-2 w-4 h-4 bg-[#eb3434] rounded-full opacity-40"></div>
      <div class="absolute -bottom-2 -left-2 w-4 h-4 bg-[#eb3434] rounded-full opacity-40"></div>
      <div class="absolute -bottom-2 -right-2 w-4 h-4 bg-[#eb3434] rounded-full opacity-60"></div>
      
      <div class="mb-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-1">Selamat Datang Kembali</h2>
        <p class="text-sm text-gray-500">Silakan masuk dengan kredensial Anda</p>
      </div>

      <form on:submit={submit} class="space-y-6">
        
        <!-- Email Field -->
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
            Alamat Email
          </label>
          <input
            id="email"
            type="email"
            bind:value={$form.email}
            required
            class="w-full px-4 py-3 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] transition duration-200"
            class:border-red-500={$form.errors.email}
            class:focus:ring-red-500={$form.errors.email}
            class:focus:border-red-500={$form.errors.email}
            placeholder="admin@ekspedisiquran.com"
          />
          {#if $form.errors.email}
            <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
              <HeroIcon name="exclamation-circle" class="w-4 h-4" />
              <span>{$form.errors.email}</span>
            </p>
          {/if}
        </div>

        <!-- Password Field -->
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
            Kata Sandi
          </label>
          <div class="relative">
            {#if showPassword}
              <input
                id="password"
                type="text"
                bind:value={$form.password}
                required
                class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] transition duration-200"
                class:border-red-500={$form.errors.password}
                class:focus:ring-red-500={$form.errors.password}
                class:focus:border-red-500={$form.errors.password}
                placeholder="••••••••"
              />
            {:else}
              <input
                id="password"
                type="password"
                bind:value={$form.password}
                required
                class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434] transition duration-200"
                class:border-red-500={$form.errors.password}
                class:focus:ring-red-500={$form.errors.password}
                class:focus:border-red-500={$form.errors.password}
                placeholder="••••••••"
              />
            {/if}
            <button
              type="button"
              on:click={togglePasswordVisibility}
              class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none transition-colors"
              aria-label={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
            >
              {#if showPassword}
                <HeroIcon name="eye-slash" class="w-5 h-5" />
              {:else}
                <HeroIcon name="eye" class="w-5 h-5" />
              {/if}
            </button>
          </div>
          {#if $form.errors.password}
            <p class="mt-2 text-sm text-red-600 flex items-center gap-1">
              <HeroIcon name="exclamation-circle" class="w-4 h-4" />
              <span>{$form.errors.password}</span>
            </p>
          {/if}
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <input
              id="remember"
              type="checkbox"
              bind:checked={$form.remember}
              class="h-4 w-4 text-[#eb3434] focus:ring-[#eb3434] border-gray-300 rounded"
            />
            <label for="remember" class="ml-2 block text-sm text-gray-700">
              Ingat saya
            </label>
          </div>
          <button type="button" class="text-sm text-[#eb3434] hover:text-red-600 transition-colors">
            Lupa kata sandi?
          </button>
        </div>

        <!-- Submit Button -->
        <div>
          <button
            type="submit"
            disabled={$form.processing}
            class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-[#eb3434] hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#eb3434] disabled:opacity-50 disabled:cursor-not-allowed transition duration-200 gap-2"
          >
            {#if $form.processing}
              <svg class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span>Memproses...</span>
            {:else}
              <HeroIcon name="arrow-right-on-rectangle" class="w-5 h-5" />
              <span>Masuk ke Dashboard</span>
            {/if}
          </button>
        </div>

        <!-- Flash Messages -->
        {#if flash.error}
          <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex items-start">
              <div class="flex-shrink-0 text-red-500 mr-3">
                <HeroIcon name="x-circle" class="h-5 w-5" />
              </div>
              <div>
                <h3 class="text-sm font-medium text-red-800">Error</h3>
                <p class="text-sm text-red-700 mt-1">{flash.error}</p>
              </div>
            </div>
          </div>
        {/if}

        {#if flash.success}
          <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-start">
              <div class="flex-shrink-0 text-green-500 mr-3">
                <HeroIcon name="check-circle" class="h-5 w-5" />
              </div>
              <div>
                <h3 class="text-sm font-medium text-green-800">Sukses</h3>
                <p class="text-sm text-green-700 mt-1">{flash.success}</p>
              </div>
            </div>
          </div>
        {/if}

        <!-- General Error Message -->
        {#if $form.errors.email}
          <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex items-start">
              <div class="flex-shrink-0 text-red-500 mr-3">
                <HeroIcon name="exclamation-triangle" class="h-5 w-5" />
              </div>
              <div>
                <h3 class="text-sm font-medium text-red-800">
                  {#if $form.errors.email.includes('dinonaktifkan')}
                    Akun Dinonaktifkan
                  {:else}
                    Login Gagal
                  {/if}
                </h3>
                <p class="text-sm text-red-700 mt-1">
                  {$form.errors.email}
                </p>
                {#if $form.errors.email.includes('dinonaktifkan')}
                  <div class="mt-2">
                    <p class="text-xs text-red-600">
                      Jika Anda merasa ini adalah kesalahan, silakan hubungi:
                    </p>
                    <div class="mt-1 text-xs text-red-600">
                      📧 admin@ekspedisiquran.com<br>
                      📞 021-12345678
                    </div>
                  </div>
                {/if}
              </div>
            </div>
          </div>
        {/if}
      </form>
    </div>

  </div>
</PublicLayout>

<style>
  /* Islamic geometric pattern */
  .islamic-geometric {
    background-image:
      repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(235, 52, 52, 0.08) 4px, rgba(235, 52, 52, 0.08) 6px),
      repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(235, 52, 52, 0.06) 4px, rgba(235, 52, 52, 0.06) 6px),
      radial-gradient(circle at 25% 25%, rgba(235, 52, 52, 0.1) 1px, transparent 1px),
      radial-gradient(circle at 75% 75%, rgba(235, 52, 52, 0.1) 1px, transparent 1px);
    background-size: 12px 12px, 12px 12px, 8px 8px, 8px 8px;
  }
</style>

