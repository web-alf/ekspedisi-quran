<script>
  import HeroIconUI from './UI/HeroIcon.svelte';
  export let name = 'document';
  export let type = 'outline';
  let className = 'w-5 h-5';
  export { className as class };
</script>

<HeroIconUI {name} {type} class={className} />
