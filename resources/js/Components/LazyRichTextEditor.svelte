<script>
  import { onMount, createEventDispatcher } from 'svelte';
  
  export let value = '';
  export let placeholder = 'Start typing...';
  export let height = 300;
  export let toolbar = 'bold italic underline | formatselect | bullist numlist | link | undo redo';
  export let loadingText = 'Loading editor...';
  export let disabled = false;
  
  const dispatch = createEventDispatcher();
  
  let editorContainer;
  let editorInstance;
  let loading = true;
  let error = null;
  let editorId = 'tinymce-' + Math.random().toString(36).substr(2, 9);

  onMount(async () => {
    try {
      // Dynamically import TinyMCE
      const { Editor } = await import('@tinymce/tinymce-svelte');
      
      // Import TinyMCE core (this might require additional setup)
      await import('tinymce');
      await import('tinymce/themes/silver');
      await import('tinymce/plugins/lists');
      await import('tinymce/plugins/link');
      
      // Initialize editor
      const editorConfig = {
        target: editorContainer,
        plugins: ['lists', 'link'],
        toolbar: toolbar,
        height: height,
        menubar: false,
        branding: false,
        statusbar: false,
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; }',
        setup: (editor) => {
          editor.on('change input', () => {
            value = editor.getContent();
            dispatch('change', { content: value });
          });
          
          editor.on('init', () => {
            editor.setContent(value);
            dispatch('ready', { editor });
            loading = false;
          });
        }
      };

      editorInstance = new Editor(editorConfig);
      
    } catch (err) {
      console.error('Failed to load rich text editor:', err);
      error = 'Failed to load editor. Falling back to basic text area.';
      loading = false;
    }
  });

  // Update editor content when value prop changes
  $: if (editorInstance && value !== editorInstance.getContent()) {
    editorInstance.setContent(value);
  }

  function handleTextareaChange(event) {
    value = event.target.value;
    dispatch('change', { content: value });
  }
</script>

<div class="w-full">
  {#if loading}
    <div class="border border-gray-300 rounded-lg p-4 bg-gray-50" style="height: {height}px">
      <div class="flex items-center justify-center h-full">
        <div class="text-center">
          <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600 mx-auto mb-2"></div>
          <p class="text-sm text-gray-600">{loadingText}</p>
        </div>
      </div>
    </div>
  {:else if error}
    <!-- Fallback to basic textarea -->
    <div class="space-y-2">
      <div class="text-sm text-orange-600 bg-orange-50 p-2 rounded border border-orange-200">
        {error}
      </div>
      <textarea
        {placeholder}
        {disabled}
        bind:value
        on:input={handleTextareaChange}
        class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y"
        style="height: {height}px"
        rows="10"
      ></textarea>
    </div>
  {:else}
    <!-- TinyMCE will be mounted here -->
    <div bind:this={editorContainer} id={editorId}></div>
  {/if}
</div>

<style>
  /* Basic textarea styling to match TinyMCE */
  textarea {
    font-family: -apple-system, BlinkMacSystemFont, 'San Francisco', 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
    font-size: 14px;
    line-height: 1.5;
  }
</style>