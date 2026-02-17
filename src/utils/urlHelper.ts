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
      singleEventEndpoint: 'event',
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

export const getEventPageURL = (eventSlug: string): string => {
  const config = getSeamlessConfig();

  if (config && config.siteUrl) {
    // Ensure site URL doesn't have trailing slash
    const baseUrl = config.siteUrl.replace(/\/$/, '');
    // Use WordPress single event endpoint
    return `${baseUrl}/${config.singleEventEndpoint}/${eventSlug}/`;
  }

  // Fallback for development
  return `/event/${eventSlug}`;
};

export const getEventURL = (
  eventTitle: string,
  eventId: string,
  eventSlug?: string
): string => {
  const slug = eventSlug || createEventSlug(eventTitle, eventId);
  return getEventPageURL(slug);
};

export const navigateToEvent = (eventSlug: string): void => {
  const url = getEventPageURL(eventSlug);
  window.location.href = url;
};

export const getEventsListURL = (): string => {
  const config = getSeamlessConfig();

  if (config) {
    return `${config.siteUrl}/${config.eventListEndpoint}`;
  }

  return '/events';
};
