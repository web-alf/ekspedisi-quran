<script>
  import { useForm } from '@inertiajs/svelte'
  import PublicLayout from '@/Layouts/PublicLayout.svelte'
  
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
      <div class="mx-auto h-20 w-20 bg-[#eb3434] rounded-2xl flex items-center justify-center mb-6 shadow-lg">
        <svg class="h-10 w-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
        </svg>
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
            <p class="mt-2 text-sm text-red-600 flex items-center">
              <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
              {$form.errors.email}
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
                <!-- Eye Slash Icon (Hide) -->
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                </svg>
              {:else}
                <!-- Eye Icon (Show) -->
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
              {/if}
            </button>
          </div>
          {#if $form.errors.password}
            <p class="mt-2 text-sm text-red-600 flex items-center">
              <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
              {$form.errors.password}
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
            class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-[#eb3434] hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#eb3434] disabled:opacity-50 disabled:cursor-not-allowed transition duration-200"
          >
            {#if $form.processing}
              <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Memproses...
            {:else}
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              Masuk ke Dashboard
            {/if}
          </button>
        </div>

        <!-- Flash Messages -->
        {#if flash.error}
          <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">Error</h3>
                <p class="text-sm text-red-700 mt-1">{flash.error}</p>
              </div>
            </div>
          </div>
        {/if}

        {#if flash.success}
          <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="ml-3">
                <h3 class="text-sm font-medium text-green-800">Sukses</h3>
                <p class="text-sm text-green-700 mt-1">{flash.success}</p>
              </div>
            </div>
          </div>
        {/if}

        <!-- General Error Message -->
        {#if $form.errors.email}
          <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="ml-3">
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

