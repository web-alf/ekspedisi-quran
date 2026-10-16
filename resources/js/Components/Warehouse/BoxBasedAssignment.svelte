<script>
  import { onMount } from 'svelte';
  import { router } from '@inertiajs/svelte';
  import AdminLayout from '../../Layouts/AdminLayout.svelte';
  import axios from 'axios';
  import { toast } from '../../utils/notifications.js';

  // Props
  export let errors = {};
  export let flash = {};
  export let auth = {};
  export let warehouseUsers = [];
  export let jenisQuran = [];
  export let assignmentPreview = null;
  export let todayTasks = [];
  export let stats = {};

  // State
  let assignments = [];
  let isLoading = false;
  let isPreviewMode = false;
  let showAssignmentModal = false;
  let selectedDate = new Date().toISOString().slice(0, 10);
  
  // Initialize assignments for each user
  onMount(() => {
    initializeAssignments();
  });

  function initializeAssignments() {
    assignments = warehouseUsers.map(user => ({
      user_id: user.id,
      user_name: user.name,
      individual_boxes: jenisQuran.reduce((acc, jenis) => {
        acc[jenis.id] = 0;
        return acc;
      }, {}),
      shared_boxes: jenisQuran.reduce((acc, jenis) => {
        acc[jenis.id] = { boxes: 0, allocated_items: 0 };
        return acc;
      }, {}),
      has_individual: false,
      has_shared: false
    }));
  }

  // Computed totals for each jenis
  $: jenisTotals = jenisQuran.reduce((totals, jenis) => {
    let individualBoxes = 0;
    let individualItems = 0;
    let sharedBoxes = 0;
    let sharedItems = 0;

    assignments.forEach(assignment => {
      // Individual boxes
      const indivBoxes = assignment.individual_boxes[jenis.id] || 0;
      individualBoxes += indivBoxes;
      individualItems += indivBoxes * jenis.capacity;

      // Shared boxes
      const sharedData = assignment.shared_boxes[jenis.id];
      if (sharedData && sharedData.boxes > 0) {
        sharedBoxes += sharedData.boxes;
        sharedItems += sharedData.allocated_items || 0;
      }
    });

    totals[jenis.id] = {
      individual_boxes: individualBoxes,
      individual_items: individualItems,
      shared_boxes: sharedBoxes,
      shared_items: sharedItems,
      total_boxes: individualBoxes + sharedBoxes,
      total_items: individualItems + sharedItems
    };
    return totals;
  }, {});

  // Update assignment flags when boxes change
  $: assignments.forEach(assignment => {
    assignment.has_individual = Object.values(assignment.individual_boxes).some(count => count > 0);
    assignment.has_shared = Object.values(assignment.shared_boxes).some(data => data.boxes > 0);
  });

  function updateIndividualBox(userIndex, jenisId, value) {
    const numValue = parseInt(value) || 0;
    assignments[userIndex].individual_boxes[jenisId] = numValue;
    assignments = [...assignments]; // Trigger reactivity
  }

  function updateSharedBox(userIndex, jenisId, field, value) {
    const numValue = parseInt(value) || 0;
    if (!assignments[userIndex].shared_boxes[jenisId]) {
      assignments[userIndex].shared_boxes[jenisId] = { boxes: 0, allocated_items: 0 };
    }
    assignments[userIndex].shared_boxes[jenisId][field] = numValue;
    assignments = [...assignments]; // Trigger reactivity
  }

  async function previewAssignment() {
    // Validate assignments
    const validAssignments = assignments.filter(assignment => 
      assignment.has_individual || assignment.has_shared
    );

    if (validAssignments.length === 0) {
      toast.error('Minimal 1 staff harus memiliki assignment');
      return;
    }

    isLoading = true;
    try {
      const response = await axios.post('/admin/supervisor/preview-box-assignment', {
        assignments: validAssignments.map(assignment => ({
          user_id: assignment.user_id,
          individual_boxes: assignment.individual_boxes,
          shared_boxes: assignment.shared_boxes
        })),
        date: selectedDate
      });

      assignmentPreview = response.data.preview;
      isPreviewMode = true;
      toast.success('Preview assignment berhasil dibuat');
    } catch (error) {
      console.error('Error previewing assignment:', error);
      toast.error('Gagal membuat preview: ' + (error.response?.data?.message || 'Unknown error'));
    } finally {
      isLoading = false;
    }
  }

  async function confirmAssignment() {
    if (!assignmentPreview) return;

    isLoading = true;
    try {
      const response = await axios.post('/admin/supervisor/assign-box-based-target', {
        assignments: assignments.filter(assignment => 
          assignment.has_individual || assignment.has_shared
        ).map(assignment => ({
          user_id: assignment.user_id,
          individual_boxes: assignment.individual_boxes,
          shared_boxes: assignment.shared_boxes
        })),
        date: selectedDate
      });

      toast.success('Assignment berhasil dibuat!');
      
      // Redirect to warehouse monitor
      router.visit('/admin/supervisor/warehouse-monitor');
    } catch (error) {
      console.error('Error confirming assignment:', error);
      toast.error('Gagal membuat assignment: ' + (error.response?.data?.message || 'Unknown error'));
    } finally {
      isLoading = false;
    }
  }

  function resetForm() {
    initializeAssignments();
    assignmentPreview = null;
    isPreviewMode = false;
  }

  function getJenisColor(jenisId) {
    const jenis = jenisQuran.find(j => j.id === jenisId);
    return jenis?.badge_class || 'bg-gray-100 text-gray-800';
  }
</script>

<AdminLayout>
  <div class="max-w-7xl mx-auto space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Box-Based Assignment</h1>
          <p class="text-gray-600">Assign boxes berdasarkan jenis Quran dengan sistem individual dan shared boxes</p>
        </div>
        <div class="flex items-center space-x-4">
          <input
            type="date"
            bind:value={selectedDate}
            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
          />
          <a 
            href="/admin/supervisor/warehouse-monitor"
            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
          >
            ← Kembali ke Monitor
          </a>
        </div>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      {#each jenisQuran as jenis}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-600">{jenis.nama_jenis}</span>
            <span class="text-xs px-2 py-1 rounded-full {getJenisColor(jenis.id)}">
              Cap: {jenis.capacity}
            </span>
          </div>
          {#if jenisTotals[jenis.id]}
            <div class="space-y-1">
              <p class="text-lg font-bold text-blue-600">
                {jenisTotals[jenis.id].total_boxes} boxes
              </p>
              <p class="text-sm text-gray-600">
                {jenisTotals[jenis.id].total_items} items total
              </p>
              <div class="text-xs text-gray-500 space-y-0.5">
                <div>Individual: {jenisTotals[jenis.id].individual_boxes} boxes</div>
                <div>Shared: {jenisTotals[jenis.id].shared_boxes} boxes</div>
              </div>
            </div>
          {:else}
            <p class="text-lg font-bold text-gray-400">0 boxes</p>
          {/if}
        </div>
      {/each}
    </div>

    {#if !isPreviewMode}
      <!-- Assignment Form -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-6">Assignment Configuration</h2>
        
        <div class="space-y-8">
          {#each assignments as assignment, userIndex}
            <div class="border border-gray-200 rounded-lg p-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-4">
                {assignment.user_name}
                {#if assignment.has_individual || assignment.has_shared}
                  <span class="ml-2 text-xs px-2 py-1 bg-green-100 text-green-800 rounded-full">
                    Active
                  </span>
                {/if}
              </h3>
              
              <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Individual Boxes -->
                <div>
                  <h4 class="text-sm font-medium text-gray-700 mb-3">Individual Boxes</h4>
                  <div class="space-y-3">
                    {#each jenisQuran as jenis}
                      <div class="flex items-center justify-between">
                        <span class="text-sm {getJenisColor(jenis.id)} px-2 py-1 rounded">
                          {jenis.nama_jenis}
                        </span>
                        <div class="flex items-center space-x-2">
                          <input
                            type="number"
                            min="0"
                            max="10"
                            value={assignment.individual_boxes[jenis.id]}
                            on:input={(e) => updateIndividualBox(userIndex, jenis.id, e.target.value)}
                            class="w-16 px-2 py-1 border border-gray-300 rounded text-center focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
                          />
                          <span class="text-xs text-gray-500">
                            = {(assignment.individual_boxes[jenis.id] || 0) * jenis.capacity} items
                          </span>
                        </div>
                      </div>
                    {/each}
                  </div>
                </div>

                <!-- Shared Boxes -->
                <div>
                  <h4 class="text-sm font-medium text-gray-700 mb-3">Shared Boxes Allocation</h4>
                  <div class="space-y-3">
                    {#each jenisQuran as jenis}
                      <div class="border border-gray-100 rounded p-3">
                        <div class="flex items-center justify-between mb-2">
                          <span class="text-sm {getJenisColor(jenis.id)} px-2 py-1 rounded">
                            {jenis.nama_jenis}
                          </span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                          <div>
                            <label class="block text-gray-600">Shared Boxes</label>
                            <input
                              type="number"
                              min="0"
                              max="5"
                              value={assignment.shared_boxes[jenis.id]?.boxes || 0}
                              on:input={(e) => updateSharedBox(userIndex, jenis.id, 'boxes', e.target.value)}
                              class="w-full px-2 py-1 border border-gray-300 rounded text-center focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
                            />
                          </div>
                          <div>
                            <label class="block text-gray-600">Allocated Items</label>
                            <input
                              type="number"
                              min="0"
                              max={jenis.capacity * 5}
                              value={assignment.shared_boxes[jenis.id]?.allocated_items || 0}
                              on:input={(e) => updateSharedBox(userIndex, jenis.id, 'allocated_items', e.target.value)}
                              class="w-full px-2 py-1 border border-gray-300 rounded text-center focus:ring-2 focus:ring-[#eb3434] focus:border-[#eb3434]"
                            />
                          </div>
                        </div>
                      </div>
                    {/each}
                  </div>
                </div>
              </div>
            </div>
          {/each}
        </div>

        <div class="flex justify-end space-x-4 mt-6 pt-6 border-t border-gray-200">
          <button
            type="button"
            on:click={resetForm}
            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
          >
            Reset
          </button>
          <button
            type="button"
            on:click={previewAssignment}
            disabled={isLoading}
            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
          >
            {isLoading ? 'Loading...' : 'Preview Assignment'}
          </button>
        </div>
      </div>
    {:else}
      <!-- Preview Mode -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-lg font-semibold text-gray-900">Assignment Preview</h2>
          <div class="flex space-x-3">
            <button
              type="button"
              on:click={() => isPreviewMode = false}
              class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors"
            >
              Edit Assignment
            </button>
            <button
              type="button"
              on:click={confirmAssignment}
              disabled={isLoading}
              class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50"
            >
              {isLoading ? 'Creating...' : 'Confirm & Create Assignment'}
            </button>
          </div>
        </div>

        {#if assignmentPreview}
          <div class="space-y-6">
            <!-- Summary -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <h3 class="font-medium text-blue-900 mb-2">Assignment Summary</h3>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                  <span class="text-blue-700">Total Users:</span>
                  <span class="font-medium ml-1">{assignmentPreview.total_users}</span>
                </div>
                <div>
                  <span class="text-blue-700">Total Boxes:</span>
                  <span class="font-medium ml-1">{assignmentPreview.total_boxes}</span>
                </div>
                <div>
                  <span class="text-blue-700">Total Items:</span>
                  <span class="font-medium ml-1">{assignmentPreview.total_items}</span>
                </div>
                <div>
                  <span class="text-blue-700">Shared Boxes:</span>
                  <span class="font-medium ml-1">{assignmentPreview.shared_boxes_count}</span>
                </div>
              </div>
            </div>

            <!-- User Details -->
            {#each assignmentPreview.user_details as userDetail}
              <div class="border border-gray-200 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-3">{userDetail.user_name}</h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <!-- Individual Boxes -->
                  {#if userDetail.individual_boxes && userDetail.individual_boxes.length > 0}
                    <div>
                      <h5 class="text-sm font-medium text-gray-700 mb-2">Individual Boxes</h5>
                      <div class="space-y-1">
                        {#each userDetail.individual_boxes as box}
                          <div class="text-sm bg-gray-50 px-3 py-2 rounded">
                            <div class="flex justify-between items-center">
                              <span class="font-mono">{box.kode_kerdus}</span>
                              <span class="{getJenisColor(box.jenis_quran_id)} px-2 py-1 rounded text-xs">
                                {box.jenis_name}
                              </span>
                            </div>
                            <div class="text-xs text-gray-600 mt-1">
                              Capacity: {box.kapasitas} items
                            </div>
                          </div>
                        {/each}
                      </div>
                    </div>
                  {/if}

                  <!-- Shared Box Allocations -->
                  {#if userDetail.shared_allocations && userDetail.shared_allocations.length > 0}
                    <div>
                      <h5 class="text-sm font-medium text-gray-700 mb-2">Shared Box Allocations</h5>
                      <div class="space-y-1">
                        {#each userDetail.shared_allocations as allocation}
                          <div class="text-sm bg-yellow-50 px-3 py-2 rounded">
                            <div class="flex justify-between items-center">
                              <span class="font-mono">{allocation.box_code}</span>
                              <span class="{getJenisColor(allocation.jenis_quran_id)} px-2 py-1 rounded text-xs">
                                {allocation.jenis_name}
                              </span>
                            </div>
                            <div class="text-xs text-gray-600 mt-1">
                              Allocated: {allocation.allocated_items} items
                            </div>
                          </div>
                        {/each}
                      </div>
                    </div>
                  {/if}
                </div>
              </div>
            {/each}
          </div>
        {/if}
      </div>
    {/if}
  </div>
</AdminLayout>