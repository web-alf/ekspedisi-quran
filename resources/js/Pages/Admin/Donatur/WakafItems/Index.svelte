<script>
  import { router, page } from '@inertiajs/svelte';
  import { useForm } from '@inertiajs/svelte';
  import { fade, scale } from 'svelte/transition';
  import AdminLayout from '../../../../Layouts/AdminLayout.svelte';
  import { can } from '../../../../utils/permissions.js';
  import { toast, dialog } from '../../../../utils/notifications.js';
  import ConfirmDialog from '../../../../Components/ConfirmDialog.svelte';
  
  // Props from backend
  export let donatur = {};
  export let items = { data: [] };
  export let stats = { statusCounts: {}, typeData: [] };
  export let can_manage = false;
  export let errors = {};
  export let flash = {};
  
  // Local state
  let showAddModal = false;
  let showEditModal = false;
  let showBulkDeleteConfirm = false;
  let selectedItems = new Set();
  let isAllSelected = false;
  let editingItem = null;
  
  // Search and filter state
  let searchQuery = '';
  let statusFilter = '';
  let typeFilter = '';
  
  // Form for adding new items
  const addForm = useForm({
    a5_quantity: 0,
    a6_quantity: 0,
    iqra_quantity: 0,
    preview_mode: false
  });
  
  // Form for bulk operations
  const bulkForm = useForm({
    wakaf_item_ids: []
  });

  // Form for editing wakaf item
  let editForm = useForm({
    wakif_name: '',
    doa_request: '',
    relationship_to_donatur: ''
  });
  
  // Check specific permissions
  $: canCreate = can_manage || can.donatur.create();
  $: canEdit = can_manage || can.donatur.update();
  $: canDelete = can_manage || can.donatur.delete();
  
  // Filtered items based on search and filters
  $: filteredItems = items.data.filter(item => {
    const matchesSearch = !searchQuery || 
      item.wakif_name?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      item.id.toString().includes(searchQuery);
    
    const matchesStatus = !statusFilter || item.status === statusFilter;
    const matchesType = !typeFilter || item.wakaf_type === typeFilter;
    
    return matchesSearch && matchesStatus && matchesType;
  });
  
  // Group items by wakaf type
  $: groupedItems = filteredItems.reduce((groups, item) => {
    if (!groups[item.wakaf_type]) {
      groups[item.wakaf_type] = [];
    }
    groups[item.wakaf_type].push(item);
    return groups;
  }, {});
  
  // Calculate total quantities for preview
  $: totalPreviewItems = $addForm.a5_quantity + $addForm.a6_quantity + $addForm.iqra_quantity;
  
  // Check if all items are selected
  $: {
    const selectableItems = filteredItems.filter(item => item.can_delete);
    isAllSelected = selectableItems.length > 0 && selectableItems.every(item => selectedItems.has(item.id));
  }
  
  // Status badge configurations
  const statusConfigs = {
    pending: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'Pending' },
    processed: { bg: 'bg-indigo-100', text: 'text-indigo-800', label: 'Sudah Diproses' },
    shipped: { bg: 'bg-green-100', text: 'text-green-800', label: 'Dikirim' },
    delivered: { bg: 'bg-emerald-100', text: 'text-emerald-800', label: 'Diterima' }
  };
  
  // Handle flash messages
  $: if (flash.success) {
    toast.success(flash.success);
  }
  $: if (flash.error) {
    toast.error(flash.error);
  }
  
  // Form submission handlers
  function handleAddItems() {
    if (totalPreviewItems === 0) {
      toast.warning("Silakan masukkan minimal 1 item untuk ditambahkan");
      return;
    }
    
    // Transform form data to match backend expectations
    const items = [];
    
    if ($addForm.a5_quantity > 0) {
      items.push({
        wakaf_type: "A5",
        quantity: $addForm.a5_quantity,
        wakif_name: donatur.prayer_mode === "customize_individual" ? null : donatur.nama_donatur,
        doa_request: donatur.prayer_mode === "semua_donatur" ? (donatur.doa_untuk_semua || "") : "",
        relationship_to_donatur: "Diri sendiri"
      });
    }
    
    if ($addForm.a6_quantity > 0) {
      items.push({
        wakaf_type: "A6",
        quantity: $addForm.a6_quantity,
        wakif_name: donatur.prayer_mode === "customize_individual" ? null : donatur.nama_donatur,
        doa_request: donatur.prayer_mode === "semua_donatur" ? (donatur.doa_untuk_semua || "") : "",
        relationship_to_donatur: "Diri sendiri"
      });
    }
    
    if ($addForm.iqra_quantity > 0) {
      items.push({
        wakaf_type: "IQRA",
        quantity: $addForm.iqra_quantity,
        wakif_name: donatur.prayer_mode === "customize_individual" ? null : donatur.nama_donatur,
        doa_request: donatur.prayer_mode === "semua_donatur" ? (donatur.doa_untuk_semua || "") : "",
        relationship_to_donatur: "Diri sendiri"
      });
    }
    
    // Debug: log the data being sent
    console.log('Sending data to backend:', { items });
    console.log('Donatur prayer_mode:', donatur.prayer_mode);
    console.log('Donatur nama:', donatur.nama_donatur);
    
    // Use router.post with transformed data instead of form.post
    router.post(`/admin/donatur/${donatur.id}/wakaf-items`, { items }, {
      onStart: () => {
        $addForm.processing = true;
      },
      onSuccess: () => {
        showAddModal = false;
        $addForm.reset();
        $addForm.processing = false;
        toast.success("Items wakaf berhasil ditambahkan");
      },
      onError: (errors) => {
        console.error("Add items error:", errors);
        $addForm.processing = false;
        toast.error("Gagal menambahkan items wakaf");
      }
    });
  }

  function handleBulkDelete() {
    const selectedItemIds = Array.from(selectedItems);
    if (selectedItemIds.length === 0) {
      toast.warning('Pilih minimal 1 item untuk dihapus');
      return;
    }
    
    // Debug logging
    console.log('Sending bulk delete request:', {
      donaturId: donatur.id,
      selectedItemIds: selectedItemIds,
      url: `/admin/donatur/${donatur.id}/wakaf-items/bulk-destroy`
    });
    
    // Use router.delete instead of $bulkForm.delete to ensure proper CSRF token handling
    router.delete(`/admin/donatur/${donatur.id}/wakaf-items/bulk-destroy`, {
      data: { 
        wakaf_item_ids: selectedItemIds,
        confirm_deletion: true,
        deletion_reason: 'Bulk delete dari admin interface'
      },
      onSuccess: () => {
        selectedItems.clear();
        selectedItems = selectedItems; // Trigger reactivity
        showBulkDeleteConfirm = false;
        toast.success(`${selectedItemIds.length} item berhasil dihapus`);
      },
      onError: (errors) => {
        console.error('Bulk delete error:', errors);
        console.error('Bulk delete error details:', JSON.stringify(errors, null, 2));
        
        // Show specific error messages if available
        let errorMessage = 'Gagal menghapus items';
        if (errors.message) {
          errorMessage = errors.message;
        } else if (errors.error) {
          errorMessage = errors.error;
        } else if (errors.wakaf_item_ids) {
          errorMessage = errors.wakaf_item_ids;
        } else if (errors.confirm_deletion) {
          errorMessage = errors.confirm_deletion;
        }
        
        toast.error(errorMessage);
      }
    });
  }

  
  // Selection handlers
  function toggleSelectAll() {
    const selectableItems = filteredItems.filter(item => item.can_delete);

    if (isAllSelected) {
      selectableItems.forEach(item => selectedItems.delete(item.id));
    } else {
      selectableItems.forEach(item => selectedItems.add(item.id));
    }
    selectedItems = selectedItems; // Trigger reactivity
  }

  function toggleSelectItem(itemId) {
    if (selectedItems.has(itemId)) {
      selectedItems.delete(itemId);
    } else {
      selectedItems.add(itemId);
    }
    selectedItems = selectedItems; // Trigger reactivity
  }

  // Edit handlers
  function openEditModal(item) {
    editingItem = item;
    $editForm.wakif_name = item.wakif_name || '';
    $editForm.doa_request = item.doa_request || '';
    $editForm.relationship_to_donatur = item.relationship_to_donatur || '';
    showEditModal = true;
  }

  function closeEditModal() {
    showEditModal = false;
    editingItem = null;
    $editForm.reset();
  }

  function saveEditModal() {
    if (!editingItem) {
      return;
    }

    $editForm.patch(`/admin/donatur/${donatur.id}/wakaf-items/${editingItem.id}`, {
      onSuccess: () => {
        closeEditModal();
        toast.success('Item wakaf berhasil diperbarui');
      },
      onError: (errors) => {
        console.error('Update error:', errors);
        toast.error('Gagal memperbarui item wakaf');
      }
    });
  }

  // Delete individual item
  async function deleteItem(item) {
    if (!item.can_delete) {
      toast.error('Item ini tidak dapat dihapus karena sudah diproses');
      return;
    }

    const confirmed = await dialog.confirmDelete(`item wakaf ${item.wakaf_type} #${item.sequence_in_type}`);

    if (confirmed) {
      router.delete(`/admin/donatur/${donatur.id}/wakaf-items/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => {
          toast.success('Item berhasil dihapus');
        },
        onError: (errors) => {
          console.error('Delete error:', errors);
          toast.error('Gagal menghapus item');
        }
      });
    }
  }
  
  // Navigation helpers
  function goBackToDonatur() {
    router.visit(`/admin/donatur/${donatur.id}/edit`);
  }
  
  function viewPengiriman(item) {
    if (item.pengiriman?.id) {
      router.visit(`/admin/pengiriman/${item.pengiriman.id}`);
    }
  }
  
  // Utility functions
  function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('id-ID', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }
  
  function getStatusBadge(status) {
    return statusConfigs[status] || statusConfigs.pending;
  }
  
  function generateWakifPreview(type, quantity) {
    if (quantity === 0) return [];
    
    const preview = [];
    for (let i = 1; i <= quantity; i++) {
      let wakifName = donatur.nama_donatur;
      if (donatur.prayer_mode === 'customize_individual') {
        wakifName = `Wakif ${type} #${i}`;
      }
      preview.push({
        type,
        sequence: i,
        wakif_name: wakifName
      });
    }
    return preview;
  }
  
  $: previewItems = [
    ...generateWakifPreview('A5', $addForm.a5_quantity),
    ...generateWakifPreview('A6', $addForm.a6_quantity),
    ...generateWakifPreview('IQRA', $addForm.iqra_quantity)
  ];
</script>

<svelte:head>
  <title>Wakaf Items - {donatur.nama_donatur} - Admin Ekspedisi Qur'an</title>
</svelte:head>

<AdminLayout>
  <!-- Header with breadcrumb -->
  <div class="mb-6 lg:mb-8">
    <nav class="flex" aria-label="Breadcrumb">
      <ol class="inline-flex items-center space-x-1 md:space-x-3">
        <li class="inline-flex items-center">
          <a href="/admin/donatur" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
            <svg class="w-3 h-3 mr-2.5" fill="currentColor" viewBox="0 0 20 20">
              <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
            </svg>
            Donatur
          </a>
        </li>
        <li>
          <div class="flex items-center">
            <svg class="w-3 h-3 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
            </svg>
            <a href="/admin/donatur/{donatur.id}/edit" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600">
              {donatur.nama_donatur}
            </a>
          </div>
        </li>
        <li aria-current="page">
          <div class="flex items-center">
            <svg class="w-3 h-3 text-gray-400 mx-1" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
            </svg>
            <span class="ml-1 text-sm font-medium text-gray-500">Wakaf Items</span>
          </div>
        </li>
      </ol>
    </nav>
    
    <div class="mt-4 flex flex-col space-y-4 lg:flex-row lg:justify-between lg:items-center lg:space-y-0">
      <div>
        <h1 class="text-xl lg:text-2xl font-bold text-gray-900">Wakaf Items</h1>
        <p class="text-gray-600">Kelola item wakaf untuk donatur: <span class="font-medium">{donatur.nama_donatur}</span></p>
        <p class="text-sm text-gray-500">Kode: {donatur.kode_donatur}</p>
      </div>
      
      <div class="flex flex-col space-y-2 sm:flex-row sm:space-y-0 sm:space-x-2">
        <button 
          on:click={goBackToDonatur}
          class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-2 lg:px-4 lg:py-2 rounded-lg text-sm font-medium transition-colors flex items-center justify-center"
        >
          <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
          </svg>
          Kembali ke Donatur
        </button>
        
        {#if canCreate}
          <button 
            on:click={() => showAddModal = true}
            class="bg-[#eb3434] hover:bg-red-600 text-white px-3 py-2 lg:px-4 lg:py-2 rounded-lg text-sm font-medium transition-colors flex items-center justify-center"
          >
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            Tambah Items
          </button>
        {/if}
      </div>
    </div>
  </div>
  
  <!-- Stats Summary -->
  <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 lg:gap-6 mb-6">
    <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6">
      <div class="flex items-center">
        <div class="w-10 h-10 lg:w-12 lg:h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 lg:w-6 lg:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
          </svg>
        </div>
        <div class="ml-3 lg:ml-4 min-w-0 flex-1">
          <p class="text-xs lg:text-sm font-medium text-gray-600 truncate">Total Items</p>
          <p class="text-lg lg:text-2xl font-bold text-gray-900">{stats.statusCounts?.total || 0}</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6">
      <div class="flex items-center">
        <div class="w-10 h-10 lg:w-12 lg:h-12 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 lg:w-6 lg:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
          </svg>
        </div>
        <div class="ml-3 lg:ml-4 min-w-0 flex-1">
          <p class="text-xs lg:text-sm font-medium text-gray-600 truncate">A5</p>
          <p class="text-lg lg:text-2xl font-bold text-gray-900">{stats.a5_count || 0}</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6">
      <div class="flex items-center">
        <div class="w-10 h-10 lg:w-12 lg:h-12 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 lg:w-6 lg:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
          </svg>
        </div>
        <div class="ml-3 lg:ml-4 min-w-0 flex-1">
          <p class="text-xs lg:text-sm font-medium text-gray-600 truncate">A6</p>
          <p class="text-lg lg:text-2xl font-bold text-gray-900">{stats.a6_count || 0}</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6">
      <div class="flex items-center">
        <div class="w-10 h-10 lg:w-12 lg:h-12 bg-orange-100 rounded-lg flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 lg:w-6 lg:h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
          </svg>
        </div>
        <div class="ml-3 lg:ml-4 min-w-0 flex-1">
          <p class="text-xs lg:text-sm font-medium text-gray-600 truncate">IQRA</p>
          <p class="text-lg lg:text-2xl font-bold text-gray-900">{stats.iqra_count || 0}</p>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6">
      <div class="flex items-center">
        <div class="w-10 h-10 lg:w-12 lg:h-12 bg-yellow-100 rounded-lg flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 lg:w-6 lg:h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <div class="ml-3 lg:ml-4 min-w-0 flex-1">
          <p class="text-xs lg:text-sm font-medium text-gray-600 truncate">Pending</p>
          <p class="text-lg lg:text-2xl font-bold text-gray-900">{stats.statusCounts?.pending || 0}</p>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Search & Filter & Actions -->
  <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100 p-4 lg:p-6 mb-6">
    <div class="space-y-4">
      <!-- Search Row -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div>
          <label for="wakafitem-search" class="block text-sm font-medium text-gray-700 mb-2">Cari Item</label>
          <input
            type="text"
            id="wakafitem-search"
            bind:value={searchQuery}
            placeholder="ID item atau nama wakif..."
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
          />
        </div>
        
        <div>
          <label for="wakafitem-status" class="block text-sm font-medium text-gray-700 mb-2">Filter Status</label>
          <select
            id="wakafitem-status"
            bind:value={statusFilter}
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
          >
            <option value="">Semua Status</option>
            <option value="pending">Pending</option>
            <option value="in_packing">In Packing</option>
            <option value="packed">Packed</option>
            <option value="shipped">Shipped</option>
            <option value="delivered">Delivered</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        
        <div>
          <label for="wakafitem-type" class="block text-sm font-medium text-gray-700 mb-2">Filter Tipe</label>
          <select
            id="wakafitem-type"
            bind:value={typeFilter}
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
          >
            <option value="">Semua Tipe</option>
            <option value="A5">A5</option>
            <option value="A6">A6</option>
            <option value="IQRA">IQRA</option>
          </select>
        </div>
      </div>
      
      <!-- Bulk Actions -->
      {#if canDelete && can_manage}
        <div class="border-t border-gray-200 pt-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
              <label class="inline-flex items-center">
                <input
                  type="checkbox"
                  checked={isAllSelected}
                  on:change={toggleSelectAll}
                  class="rounded border-gray-300 text-[#eb3434] shadow-sm focus:border-[#eb3434] focus:ring focus:ring-[#eb3434] focus:ring-opacity-50"
                />
                <span class="ml-2 text-sm text-gray-700">Pilih Semua Pending</span>
              </label>
              
              {#if selectedItems.size > 0}
                <span class="text-sm text-gray-600">
                  {selectedItems.size} item dipilih
                </span>
              {/if}
            </div>
            
            {#if selectedItems.size > 0}
              <button
                on:click={() => showBulkDeleteConfirm = true}
                class="bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1 rounded-md text-sm font-medium transition-colors"
              >
                Hapus Terpilih
              </button>
            {/if}
          </div>
        </div>
      {/if}
    </div>
  </div>

  <!-- Items Table/Cards -->
  <div class="bg-white rounded-lg lg:rounded-xl shadow-sm border border-gray-100">
    <div class="px-4 lg:px-6 py-4 border-b border-gray-200">
      <h3 class="text-lg font-semibold text-gray-900">Daftar Item Wakaf</h3>
      <p class="text-sm text-gray-500">Total: {filteredItems.length} item</p>
    </div>
    
    <!-- Desktop Table -->
    <div class="hidden lg:block overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            {#if canDelete && can_manage}
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-10">
                <input
                  type="checkbox"
                  checked={isAllSelected}
                  on:change={toggleSelectAll}
                  class="rounded border-gray-300 text-[#eb3434] shadow-sm focus:border-[#eb3434] focus:ring focus:ring-[#eb3434] focus:ring-opacity-50"
                />
              </th>
            {/if}
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe Wakaf</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Wakif</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dibuat</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          {#each Object.entries(groupedItems) as [type, typeItems]}
            <!-- Group Header -->
            <tr class="bg-gray-25">
              <td colspan={canDelete && can_manage ? "7" : "6"} class="px-6 py-3">
                <div class="flex items-center">
                  <span class="text-sm font-medium text-gray-900">{type}</span>
                  <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    {typeItems.length} items
                  </span>
                </div>
              </td>
            </tr>
            
            {#each typeItems as item}
              <tr class="hover:bg-gray-50">
                {#if canDelete && can_manage}
                  <td class="px-6 py-4 whitespace-nowrap">
                    {#if item.can_delete}
                      <input
                        type="checkbox"
                        checked={selectedItems.has(item.id)}
                        on:change={() => toggleSelectItem(item.id)}
                        class="rounded border-gray-300 text-[#eb3434] shadow-sm focus:border-[#eb3434] focus:ring focus:ring-[#eb3434] focus:ring-opacity-50"
                      />
                    {/if}
                  </td>
                {/if}
                
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">{item.wakaf_type} #{item.sequence_in_type}</div>
                  <div class="text-xs text-gray-500">ID: {item.id}</div>
                </td>
                
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                      <span class="text-xs font-medium text-blue-800">{item.wakaf_type}</span>
                    </div>
                    <div>
                      <div class="text-sm font-medium text-gray-900">{item.wakaf_type}</div>
                      <div class="text-xs text-gray-500">#{item.sequence_in_type}</div>
                    </div>
                  </div>
                </td>
                
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-900">{item.wakif_name || 'Belum diisi'}</span>
                    {#if canEdit && can_manage && item.can_edit}
                      <button
                        on:click={() => openEditModal(item)}
                        class="text-blue-600 hover:text-blue-800 p-1"
                        title="Edit item wakaf"
                      >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                      </button>
                    {/if}
                  </div>
                </td>
                
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {getStatusBadge(item.status).bg} {getStatusBadge(item.status).text}">
                    {getStatusBadge(item.status).label}
                  </span>
                </td>
                
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{formatDate(item.created_at_iso || item.created_at)}</div>
                </td>
                
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex justify-end space-x-2">
                    {#if item.pengiriman_id}
                      <button
                        on:click={() => viewPengiriman(item)}
                        class="text-blue-600 hover:text-blue-900 p-1 hover:bg-blue-50 rounded transition-colors"
                        title="Lihat Pengiriman"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                      </button>
                    {/if}
                    
                    {#if canDelete && item.can_delete}
                      <button
                        on:click={() => deleteItem(item)}
                        class="text-red-600 hover:text-red-900 p-1 hover:bg-red-50 rounded transition-colors"
                        title="Hapus"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                      </button>
                    {/if}
                  </div>
                </td>
              </tr>
            {/each}
          {:else}
            <tr>
              <td colspan={canDelete && can_manage ? "7" : "6"} class="px-6 py-12 text-center">
                <div class="text-gray-500">
                  <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                  </svg>
                  <p class="text-lg font-medium">Tidak ada item ditemukan</p>
                  <p class="text-sm">
                    {#if searchQuery || statusFilter || typeFilter}
                      Coba ubah filter atau
                    {/if}
                    {#if canCreate && can_manage}
                      <button on:click={() => showAddModal = true} class="text-blue-600 hover:text-blue-800 underline">tambah item baru</button>
                    {/if}
                  </p>
                </div>
              </td>
            </tr>
          {/each}
        </tbody>
      </table>
    </div>

    <!-- Mobile Cards -->
    <div class="lg:hidden">
      {#each Object.entries(groupedItems) as [type, typeItems]}
        <!-- Type Header -->
        <div class="border-b border-gray-200 bg-gray-50 px-4 py-3">
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-gray-900">{type}</span>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
              {typeItems.length} items
            </span>
          </div>
        </div>
        
        {#each typeItems as item}
          <div class="border-b border-gray-200 p-4 hover:bg-gray-50 transition-colors">
            <div class="flex items-start space-x-3">
              {#if canDelete && can_manage && item.can_delete}
                <input
                  type="checkbox"
                  checked={selectedItems.has(item.id)}
                  on:change={() => toggleSelectItem(item.id)}
                  class="mt-1 rounded border-gray-300 text-[#eb3434] shadow-sm focus:border-[#eb3434] focus:ring focus:ring-[#eb3434] focus:ring-opacity-50"
                />
              {/if}
              
              <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0">
                <span class="text-sm font-medium text-blue-800">{item.wakaf_type}</span>
              </div>
              
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between">
                  <div class="flex-1 min-w-0">
                    <h4 class="text-sm font-medium text-gray-900">{item.wakaf_type} #{item.sequence_in_type}</h4>
                    
                    <div class="mt-1 flex items-center space-x-2">
                      <p class="text-sm text-gray-600">{item.wakif_name || 'Belum diisi'}</p>
                      {#if canEdit && can_manage && item.can_edit}
                        <button
                          on:click={() => openEditModal(item)}
                          class="text-blue-600 hover:text-blue-800 p-1"
                          title="Edit item wakaf"
                        >
                          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                          </svg>
                        </button>
                      {/if}
                    </div>
                  </div>
                  
                  <div class="flex space-x-1 ml-2 flex-shrink-0">
                    {#if item.pengiriman_id}
                      <button
                        on:click={() => viewPengiriman(item)}
                        class="text-blue-600 hover:text-blue-900 p-2 hover:bg-blue-50 rounded-full transition-colors"
                        title="Lihat Pengiriman"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                      </button>
                    {/if}
                    
                    {#if canDelete && can_manage && item.can_delete}
                      <button
                        on:click={() => deleteItem(item)}
                        class="text-red-600 hover:text-red-900 p-2 hover:bg-red-50 rounded-full transition-colors"
                        title="Hapus"
                      >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                      </button>
                    {/if}
                  </div>
                </div>
                
                <div class="mt-2 flex items-center space-x-2">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {getStatusBadge(item.status).bg} {getStatusBadge(item.status).text}">
                    {getStatusBadge(item.status).label}
                  </span>
                </div>
                
                <div class="mt-2 text-xs text-gray-500">
                  <div>Dibuat: {formatDate(item.created_at_iso || item.created_at)}</div>
                </div>
              </div>
            </div>
          </div>
        {/each}
      {:else}
        <div class="p-8 text-center">
          <div class="text-gray-500">
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            <p class="text-lg font-medium">Tidak ada item ditemukan</p>
            <p class="text-sm">
              {#if searchQuery || statusFilter || typeFilter}
                Coba ubah filter atau
              {/if}
              {#if canCreate && can_manage}
                <button on:click={() => showAddModal = true} class="text-blue-600 hover:text-blue-800 underline">tambah item baru</button>
              {/if}
            </p>
          </div>
        </div>
      {/each}
    </div>
    
    <!-- Pagination -->
    {#if items.last_page > 1}
      <div class="px-4 sm:px-6 py-4 border-t border-gray-200">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
          <div class="text-sm text-gray-500 text-center sm:text-left">
            Menampilkan {items.from} - {items.to} dari {items.total} hasil
          </div>
          <div class="flex justify-center sm:justify-end items-center space-x-1">
            <!-- Previous -->
            {#if items.prev_page_url}
              <button
                on:click={() => router.visit(items.prev_page_url, { preserveScroll: true })}
                class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md flex items-center"
              >
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                <span class="hidden sm:inline">Sebelumnya</span>
                <span class="sm:hidden">Prev</span>
              </button>
            {:else}
              <span class="px-3 py-2 text-sm text-gray-300 cursor-not-allowed rounded-md flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                <span class="hidden sm:inline">Sebelumnya</span>
                <span class="sm:hidden">Prev</span>
              </span>
            {/if}
            
            <!-- Page Numbers -->
            {#each Array(Math.min(5, items.last_page)).fill().map((_, i) => i + Math.max(1, items.current_page - 2)) as pageNum}
              {#if pageNum <= items.last_page}
                <button
                  on:click={() => router.visit(`/admin/donatur/${donatur.id}/wakaf-items?page=${pageNum}`, { preserveScroll: true })}
                  class="px-3 py-2 text-sm rounded-md {pageNum === items.current_page ? 'bg-red-600 text-white font-medium' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'}"
                >
                  {pageNum}
                </button>
              {/if}
            {/each}
            
            <!-- Next -->
            {#if items.next_page_url}
              <button
                on:click={() => router.visit(items.next_page_url, { preserveScroll: true })}
                class="px-3 py-2 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md flex items-center"
              >
                <span class="hidden sm:inline">Selanjutnya</span>
                <span class="sm:hidden">Next</span>
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </button>
            {:else}
              <span class="px-3 py-2 text-sm text-gray-300 cursor-not-allowed rounded-md flex items-center">
                <span class="hidden sm:inline">Selanjutnya</span>
                <span class="sm:hidden">Next</span>
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
              </span>
            {/if}
          </div>
        </div>
      </div>
    {/if}
  </div>
</AdminLayout>

<!-- Add Items Modal -->
{#if showAddModal}
  <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50" transition:fade>
    <div class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div 
          class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl"
          transition:scale={{ duration: 200, start: 0.95 }}
        >
          <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
              </div>
              
              <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                <h3 class="text-lg font-semibold leading-6 text-gray-900 mb-4">
                  Tambah Item Wakaf
                </h3>
                
                <div class="space-y-4">
                  <!-- Quantity Inputs -->
                  <div class="grid grid-cols-3 gap-4">
                    <div>
                      <label for="add-a5" class="block text-sm font-medium text-gray-700 mb-2">
                        Al-Qur'an A5
                      </label>
                      <input
                        id="add-a5"
                        type="number"
                        min="0"
                        max="100"
                        bind:value={$addForm.a5_quantity}
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="0"
                      />
                    </div>
                    
                    <div>
                      <label for="add-a6" class="block text-sm font-medium text-gray-700 mb-2">
                        Al-Qur'an A6
                      </label>
                      <input
                        id="add-a6"
                        type="number"
                        min="0"
                        max="100"
                        bind:value={$addForm.a6_quantity}
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="0"
                      />
                    </div>
                    
                    <div>
                      <label for="add-iqra" class="block text-sm font-medium text-gray-700 mb-2">
                        IQRA
                      </label>
                      <input
                        id="add-iqra"
                        type="number"
                        min="0"
                        max="100"
                        bind:value={$addForm.iqra_quantity}
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="0"
                      />
                    </div>
                  </div>
                  
                  <!-- Preview -->
                  {#if totalPreviewItems > 0}
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                      <h4 class="text-sm font-medium text-blue-900 mb-3">
                        Preview Items ({totalPreviewItems} total)
                      </h4>
                      
                      <div class="max-h-40 overflow-y-auto space-y-1">
                        {#each previewItems as item, index}
                          <div class="flex items-center justify-between text-sm">
                            <span class="text-blue-800">
                              {item.type} #{item.sequence}
                            </span>
                            <span class="text-blue-600">
                              {item.wakif_name}
                            </span>
                          </div>
                        {/each}
                      </div>
                      
                      {#if donatur.prayer_mode === 'semua_donatur'}
                        <div class="mt-2 text-xs text-blue-700">
                          Semua item akan menggunakan nama donatur: <strong>{donatur.nama_donatur}</strong>
                        </div>
                      {:else}
                        <div class="mt-2 text-xs text-blue-700">
                          Nama wakif dapat diedit setelah item dibuat
                        </div>
                      {/if}
                    </div>
                  {/if}
                  
                  <!-- Validation Messages -->
                  {#if errors.a5_quantity}
                    <p class="text-sm text-red-600">{errors.a5_quantity}</p>
                  {/if}
                  {#if errors.a6_quantity}
                    <p class="text-sm text-red-600">{errors.a6_quantity}</p>
                  {/if}
                  {#if errors.iqra_quantity}
                    <p class="text-sm text-red-600">{errors.iqra_quantity}</p>
                  {/if}
                </div>
              </div>
            </div>
          </div>
          
          <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
            <button
              type="button"
              on:click={handleAddItems}
              disabled={$addForm.processing || totalPreviewItems === 0}
              class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed sm:ml-3 sm:w-auto"
            >
              {#if $addForm.processing}
                <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Menambahkan...
              {:else}
                Tambah Items
              {/if}
            </button>
            
            <button
              type="button"
              on:click={() => { showAddModal = false; $addForm.reset(); }}
              class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto"
            >
              Batal
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
{/if}

<!-- Edit Item Modal -->
{#if showEditModal}
  <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity z-50" transition:fade>
    <div class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
        <div
          class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg"
          transition:scale={{ duration: 200, start: 0.95 }}
        >
          <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
              </div>

              <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                <h3 class="text-lg font-semibold leading-6 text-gray-900 mb-4">
                  Edit Item Wakaf
                </h3>

                <div class="space-y-4">
                  <div>
                    <label for="edit-wakif-name" class="block text-sm font-medium text-gray-700 mb-1">
                      Nama Wakif
                    </label>
                    <input
                      id="edit-wakif-name"
                      type="text"
                      bind:value={$editForm.wakif_name}
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="Nama wakif"
                    />
                  </div>

                  <div>
                    <label for="edit-relationship" class="block text-sm font-medium text-gray-700 mb-1">
                      Hubungan dengan Donatur
                    </label>
                    <input
                      id="edit-relationship"
                      type="text"
                      bind:value={$editForm.relationship_to_donatur}
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="Diri sendiri"
                    />
                  </div>

                  <div>
                    <label for="edit-doa" class="block text-sm font-medium text-gray-700 mb-1">
                      Doa / Permintaan Khusus
                    </label>
                    <textarea
                      id="edit-doa"
                      bind:value={$editForm.doa_request}
                      rows="3"
                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      placeholder="Doa untuk muwakif"
                    ></textarea>
                  </div>

                  {#if errors.wakif_name}
                    <p class="text-sm text-red-600">{errors.wakif_name}</p>
                  {/if}
                  {#if errors.doa_request}
                    <p class="text-sm text-red-600">{errors.doa_request}</p>
                  {/if}
                  {#if errors.relationship_to_donatur}
                    <p class="text-sm text-red-600">{errors.relationship_to_donatur}</p>
                  {/if}
                </div>
              </div>
            </div>
          </div>

          <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
            <button
              type="button"
              on:click={saveEditModal}
              disabled={$editForm.processing}
              class="inline-flex w-full justify-center rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed sm:ml-3 sm:w-auto"
            >
              {#if $editForm.processing}
                <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Menyimpan...
              {:else}
                Simpan
              {/if}
            </button>

            <button
              type="button"
              on:click={closeEditModal}
              class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto"
            >
              Batal
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
{/if}

<!-- Bulk Delete Confirmation -->
<ConfirmDialog
  bind:show={showBulkDeleteConfirm}
  title="Hapus Items Terpilih"
  message="Apakah Anda yakin ingin menghapus {selectedItems.size} item yang dipilih? Tindakan ini tidak dapat dibatalkan."
  type="danger"
  confirmText="Hapus"
  cancelText="Batal"
  on:confirm={handleBulkDelete}
/>
