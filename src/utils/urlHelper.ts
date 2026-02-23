export interface EventURLConfig {
  siteUrl: string;
  singleEventEndpoint: string;
  eventListEndpoint: string;
}

export const getWordPressSiteUrl = (): string => {
  // First, check if passed via window object (preferred method)
  if (typeof window !== 'undefined') {
    // Check for seamless config
    if ((window as any).seamlessConfig?.siteUrl) {
      return (window as any).seamlessConfig.siteUrl;
    }

    // Check for WordPress REST API base URL in meta tag
    const restApiMeta = document.querySelector('meta[name="rest-api-base-url"]');
    if (restApiMeta) {
      let siteUrl = restApiMeta.getAttribute('content');
      // Remove /wp-json/wp/v2 or similar REST endpoints to get base URL
      if (siteUrl && siteUrl.includes('/wp-json')) {
        siteUrl = siteUrl.split('/wp-json')[0];
      }
      return siteUrl || window.location.origin;
    }

    // Check for WordPress site URL in meta tag
    const siteMeta = document.querySelector('meta[name="wordpress-site-url"]');
    if (siteMeta) {
      const siteUrl = siteMeta.getAttribute('content');
      if (siteUrl) return siteUrl;
    }

    // Fallback to window location origin
    return window.location.origin;
  }

  return '';
};

export const getSeamlessConfig = (): EventURLConfig | null => {
  if (typeof window !== 'undefined' && (window as any).seamlessConfig) {
    return (window as any).seamlessConfig;
  }

  // Dynamically create config if not explicitly set
  const siteUrl = getWordPressSiteUrl();
  if (siteUrl) {
    return {
      siteUrl,
      singleEventEndpoint: 'events',
      eventListEndpoint: 'events'
    };
  }

  return null;
};

export const createEventSlug = (title: string, id: string): string => {
  return title
    .toLowerCase()
    .replace(/[^\w\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
    .trim() || id;
};

export const getEventPageURL = (eventSlug: string, isGroupEvent: boolean = false): string => {
  // Use query-parameter deep-linking — the same strategy as Card.tsx —
  // so calendar clicks produce identical URLs to grid/list card clicks.
  // Format: ?seamless_event=SLUG&type=events|group-event
  // This stays on the current WordPress page (where the shortcode is mounted)
  // and signals to the React MemoryRouter which event to show.
  const type = isGroupEvent ? 'group-event' : 'events';
  return `?seamless_event=${encodeURIComponent(eventSlug)}&type=${type}`;
};

export const getEventURL = (
  eventTitle: string,
  eventId: string,
  eventSlug?: string,
  isGroupEvent?: boolean
): string => {
  const slug = eventSlug || createEventSlug(eventTitle, eventId);
  return getEventPageURL(slug, isGroupEvent);
};

export const navigateToEvent = (eventSlug: string, isGroupEvent?: boolean): void => {
  const url = getEventPageURL(eventSlug, isGroupEvent);
  // Update the real browser URL bar (MemoryRouter won't do it itself)
  try {
    const realParams = new URLSearchParams(window.location.search);
    realParams.set('seamless_event', eventSlug);
    realParams.set('type', isGroupEvent ? 'group-event' : 'events');
    history.pushState(null, '', `?${realParams.toString()}`);
  } catch {
    // Fallback: direct navigate
    window.location.href = url;
    return;
  }
  // Trigger a same-page navigation so React picks up the new query params
  window.location.href = url;
};


export const getEventsListURL = (): string => {
  const config = getSeamlessConfig();

  if (config) {
    return `${config.siteUrl}/${config.eventListEndpoint}`;
  }

  return '/events';
};
