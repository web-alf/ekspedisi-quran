<script>
  import { formatPageTitle, generateMetaDescription } from '@/utils/seo.js';
  
  export let title = '';
  export let description = '';
  export let keywords = '';
  export let image = '';
  export let url = '';
  export let type = 'website';
  export let pageIdentifier = '';
  
  // Generate title and description if not provided
  $: computedTitle = title || (pageIdentifier ? formatPageTitle(pageIdentifier) : 'Ekspedisi Quran');
  $: computedDescription = description || (pageIdentifier ? generateMetaDescription(pageIdentifier) : 'Platform wakaf dan distribusi mushaf Al-Quran untuk seluruh Indonesia');
  $: computedImage = image || '/images/og-image.jpg';
  $: computedUrl = url || (typeof window !== 'undefined' ? window.location.href : '');
</script>

<svelte:head>
  <title>{computedTitle}</title>
  <meta name="description" content="{computedDescription}">
  
  {#if keywords}
    <meta name="keywords" content="{keywords}">
  {/if}
  
  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="{type}">
  <meta property="og:url" content="{computedUrl}">
  <meta property="og:title" content="{computedTitle}">
  <meta property="og:description" content="{computedDescription}">
  <meta property="og:image" content="{computedImage}">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:site_name" content="Ekspedisi Quran">
  <meta property="og:locale" content="id_ID">
  
  <!-- Twitter -->
  <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:url" content="{computedUrl}">
  <meta property="twitter:title" content="{computedTitle}">
  <meta property="twitter:description" content="{computedDescription}">
  <meta property="twitter:image" content="{computedImage}">
  
  <!-- Additional meta tags -->
  <meta name="author" content="Ekspedisi Quran">
  <link rel="canonical" href="{computedUrl}">
</svelte:head>