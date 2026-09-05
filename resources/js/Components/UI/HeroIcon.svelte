<script>
  /**
   * HeroIcon.svelte — thin Lucide adapter.
   *
   * Semua ikon dirender oleh lucide-svelte (Lucide icon set). Nama ikon
   * memakai konvensi Heroicons v2 (kebab-case), dipetakan ke komponen
   * Lucide yang bersangkutan via subpath import per-ikon.
   *
   * Props: `name` (wajib), `type` ('outline'|'solid' — Lucide stroke),
   * `size` (default 24), `strokeWidth`, `class`.
   */
  import IconAcademicCap from 'lucide-svelte/icons/graduation-cap';
  import IconAdjustmentsHorizontal from 'lucide-svelte/icons/sliders-horizontal';
  import IconArrowDownTray from 'lucide-svelte/icons/download';
  import IconArrowLeft from 'lucide-svelte/icons/arrow-left';
  import IconArrowPath from 'lucide-svelte/icons/refresh-cw';
  import IconArrowRight from 'lucide-svelte/icons/arrow-right';
  import IconArrowRightOnRectangle from 'lucide-svelte/icons/log-out';
  import IconArrowTopRightOnSquare from 'lucide-svelte/icons/external-link';
  import IconArrowTrendingUp from 'lucide-svelte/icons/trending-up';
  import IconArrowUpTray from 'lucide-svelte/icons/upload';
  import IconBars3 from 'lucide-svelte/icons/menu';
  import IconBell from 'lucide-svelte/icons/bell';
  import IconBolt from 'lucide-svelte/icons/zap';
  import IconBookOpen from 'lucide-svelte/icons/book-open';
  import IconBuildingOffice2 from 'lucide-svelte/icons/building-2';
  import IconCalendar from 'lucide-svelte/icons/calendar';
  import IconCalendarDays from 'lucide-svelte/icons/calendar-days';
  import IconCamera from 'lucide-svelte/icons/camera';
  import IconChartBar from 'lucide-svelte/icons/bar-chart-3';
  import IconChartPie from 'lucide-svelte/icons/pie-chart';
  import IconChatBubbleLeftRight from 'lucide-svelte/icons/message-square';
  import IconCheck from 'lucide-svelte/icons/check';
  import IconCheckCircle from 'lucide-svelte/icons/circle-check';
  import IconChevronDown from 'lucide-svelte/icons/chevron-down';
  import IconChevronLeft from 'lucide-svelte/icons/chevron-left';
  import IconChevronRight from 'lucide-svelte/icons/chevron-right';
  import IconChevronUp from 'lucide-svelte/icons/chevron-up';
  import IconClipboardDocument from 'lucide-svelte/icons/clipboard';
  import IconClipboardDocumentCheck from 'lucide-svelte/icons/clipboard-check';
  import IconClipboardDocumentList from 'lucide-svelte/icons/list-checks';
  import IconClock from 'lucide-svelte/icons/clock';
  import IconCog6Tooth from 'lucide-svelte/icons/settings';
  import IconCpuChip from 'lucide-svelte/icons/cpu';
  import IconCube from 'lucide-svelte/icons/box';
  import IconDocumentChartBar from 'lucide-svelte/icons/chart-column';
  import IconDocumentText from 'lucide-svelte/icons/file-text';
  import IconEnvelope from 'lucide-svelte/icons/mail';
  import IconExclamationCircle from 'lucide-svelte/icons/circle-alert';
  import IconExclamationTriangle from 'lucide-svelte/icons/triangle-alert';
  import IconEye from 'lucide-svelte/icons/eye';
  import IconEyeSlash from 'lucide-svelte/icons/eye-off';
  import IconFacebook from 'lucide-svelte/icons/facebook';
  import IconFolder from 'lucide-svelte/icons/folder';
  import IconGlobeAlt from 'lucide-svelte/icons/globe';
  import IconHome from 'lucide-svelte/icons/house';
  import IconInboxStack from 'lucide-svelte/icons/inbox';
  import IconInformationCircle from 'lucide-svelte/icons/info';
  import IconInstagram from 'lucide-svelte/icons/instagram';
  import IconKey from 'lucide-svelte/icons/key';
  import IconLightBulb from 'lucide-svelte/icons/lightbulb';
  import IconLockClosed from 'lucide-svelte/icons/lock';
  import IconMagnifyingGlass from 'lucide-svelte/icons/search';
  import IconMap from 'lucide-svelte/icons/map';
  import IconMapPin from 'lucide-svelte/icons/map-pin';
  import IconMessageCircle from 'lucide-svelte/icons/message-circle';
  import IconMinus from 'lucide-svelte/icons/minus';
  import IconPaintBrush from 'lucide-svelte/icons/brush';
  import IconPaperAirplane from 'lucide-svelte/icons/send';
  import IconPause from 'lucide-svelte/icons/pause';
  import IconPencil from 'lucide-svelte/icons/pencil';
  import IconPencilSquare from 'lucide-svelte/icons/square-pen';
  import IconPhone from 'lucide-svelte/icons/phone';
  import IconPhoto from 'lucide-svelte/icons/image';
  import IconPlay from 'lucide-svelte/icons/play';
  import IconPlus from 'lucide-svelte/icons/plus';
  import IconPrinter from 'lucide-svelte/icons/printer';
  import IconQrCode from 'lucide-svelte/icons/qr-code';
  import IconQuestionMarkCircle from 'lucide-svelte/icons/circle-help';
  import IconQuote from 'lucide-svelte/icons/quote';
  import IconShare from 'lucide-svelte/icons/share-2';
  import IconShieldCheck from 'lucide-svelte/icons/shield-check';
  import IconSparkles from 'lucide-svelte/icons/sparkles';
  import IconStar from 'lucide-svelte/icons/star';
  import IconStop from 'lucide-svelte/icons/square';
  import IconSun from 'lucide-svelte/icons/sun';
  import IconTag from 'lucide-svelte/icons/tag';
  import IconTrash from 'lucide-svelte/icons/trash-2';
  import IconTruck from 'lucide-svelte/icons/truck';
  import IconTwitter from 'lucide-svelte/icons/twitter';
  import IconUser from 'lucide-svelte/icons/user';
  import IconUserGroup from 'lucide-svelte/icons/users';
  import IconUsers from 'lucide-svelte/icons/users';
  import IconVideoCamera from 'lucide-svelte/icons/video';
  import IconXCircle from 'lucide-svelte/icons/circle-x';
  import IconXMark from 'lucide-svelte/icons/x';
  import IconYoutube from 'lucide-svelte/icons/youtube';

  const iconMap = {
    'academic-cap': IconAcademicCap,
    'adjustments-horizontal': IconAdjustmentsHorizontal,
    'arrow-down-tray': IconArrowDownTray,
    'arrow-left': IconArrowLeft,
    'arrow-path': IconArrowPath,
    'arrow-right': IconArrowRight,
    'arrow-right-on-rectangle': IconArrowRightOnRectangle,
    'arrow-top-right-on-square': IconArrowTopRightOnSquare,
    'arrow-trending-up': IconArrowTrendingUp,
    'arrow-up-tray': IconArrowUpTray,
    'bars-3': IconBars3,
    'bell': IconBell,
    'bolt': IconBolt,
    'book-open': IconBookOpen,
    'building-office-2': IconBuildingOffice2,
    'calendar': IconCalendar,
    'calendar-days': IconCalendarDays,
    'camera': IconCamera,
    'chart-bar': IconChartBar,
    'chart-pie': IconChartPie,
    'chat-bubble-left-right': IconChatBubbleLeftRight,
    'check': IconCheck,
    'check-circle': IconCheckCircle,
    'chevron-down': IconChevronDown,
    'chevron-left': IconChevronLeft,
    'chevron-right': IconChevronRight,
    'chevron-up': IconChevronUp,
    'clipboard-document': IconClipboardDocument,
    'clipboard-document-check': IconClipboardDocumentCheck,
    'clipboard-document-list': IconClipboardDocumentList,
    'clock': IconClock,
    'cog-6-tooth': IconCog6Tooth,
    'cpu-chip': IconCpuChip,
    'cube': IconCube,
    'document-chart-bar': IconDocumentChartBar,
    'document-text': IconDocumentText,
    'envelope': IconEnvelope,
    'exclamation-circle': IconExclamationCircle,
    'exclamation-triangle': IconExclamationTriangle,
    'eye': IconEye,
    'eye-slash': IconEyeSlash,
    'facebook': IconFacebook,
    'folder': IconFolder,
    'globe-alt': IconGlobeAlt,
    'home': IconHome,
    'inbox-stack': IconInboxStack,
    'information-circle': IconInformationCircle,
    'instagram': IconInstagram,
    'key': IconKey,
    'light-bulb': IconLightBulb,
    'lock-closed': IconLockClosed,
    'magnifying-glass': IconMagnifyingGlass,
    'map': IconMap,
    'map-pin': IconMapPin,
    'message-circle': IconMessageCircle,
    'minus': IconMinus,
    'paint-brush': IconPaintBrush,
    'paper-airplane': IconPaperAirplane,
    'pause': IconPause,
    'pencil': IconPencil,
    'pencil-square': IconPencilSquare,
    'phone': IconPhone,
    'photo': IconPhoto,
    'play': IconPlay,
    'plus': IconPlus,
    'printer': IconPrinter,
    'qr-code': IconQrCode,
    'question-mark-circle': IconQuestionMarkCircle,
    'quote': IconQuote,
    'share': IconShare,
    'shield-check': IconShieldCheck,
    'sparkles': IconSparkles,
    'star': IconStar,
    'stop': IconStop,
    'sun': IconSun,
    'tag': IconTag,
    'trash': IconTrash,
    'truck': IconTruck,
    'twitter': IconTwitter,
    'user': IconUser,
    'user-group': IconUserGroup,
    'users': IconUsers,
    'video-camera': IconVideoCamera,
    'x-circle': IconXCircle,
    'x-mark': IconXMark,
    'youtube': IconYoutube
  };

  export let name = 'document-text';
  export let type = 'outline'; // 'outline' | 'solid' (Lucide: stroke-width)
  export let size = 24;
  export let strokeWidth = undefined;
  let className = '';
  export { className as class };

  $: Component = iconMap[name] || IconDocumentText;
  $: resolvedStrokeWidth = strokeWidth ?? (type === 'solid' ? 2 : 2);
</script>

<svelte:component
  this={Component}
  {size}
  strokeWidth={resolvedStrokeWidth}
  class={className || undefined}
/>
