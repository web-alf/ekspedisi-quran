<script>
  import { onMount, createEventDispatcher } from 'svelte';
  import Editor from '@tinymce/tinymce-svelte';

  export let value = '';
  export let placeholder = 'Masukkan konten...';
  export let height = 300;
  export let disabled = false;
  
  const dispatch = createEventDispatcher();
  
  let conf = {
    height: height,
    menubar: false,
    plugins: [
      'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
      'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
      'insertdatetime', 'media', 'table', 'code', 'help', 'wordcount'
    ],
    toolbar: 'undo redo | blocks | ' +
      'bold italic forecolor | alignleft aligncenter ' +
      'alignright alignjustify | bullist numlist outdent indent | ' +
      'removeformat | help',
    content_style: 'body { font-family: Cairo, Helvetica, Arial, sans-serif; font-size:14px }',
    placeholder: placeholder,
    readonly: disabled,
    setup: (editor) => {
      editor.on('change', () => {
        value = editor.getContent();
        dispatch('change', value);
      });
    }
  };

  function handleEditorChange(event) {
    value = event.detail.getContent();
    dispatch('change', value);
  }
</script>

<div class="rich-text-editor">
  <Editor
    {value}
    {conf}
    on:change={handleEditorChange}
  />
</div>

<style>
  :global(.tox-tinymce) {
    border-radius: 8px !important;
    border: 1px solid #d1d5db !important;
  }
  
  :global(.tox-tinymce:focus-within) {
    border-color: #eb3434 !important;
    box-shadow: 0 0 0 2px rgba(235, 52, 52, 0.2) !important;
  }
  
  :global(.tox-toolbar) {
    border-radius: 8px 8px 0 0 !important;
  }
  
  :global(.tox-edit-area) {
    border-radius: 0 0 8px 8px !important;
  }
</style>