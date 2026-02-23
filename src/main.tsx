import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'

/**
 * Seamless React Plugin – Multi-mount entry point
 *
 * Supports two modes:
 *  1. WordPress shortcode mode – each shortcode renders a div with a specific
 *     `data-seamless-view` attribute that tells the app which route to show.
 *  2. Dev / standalone mode – falls back to the #root container and renders the
 *     full BrowserRouter-based SPA.
 *
 * Each WordPress shortcode mounts its own isolated React root so that
 * multiple shortcodes can coexist on the same page without conflicts.
 */

// Detect all Seamless shortcode containers on the page
const shortcodeContainers = document.querySelectorAll<HTMLElement>('[data-seamless-view]');

if (shortcodeContainers.length > 0) {
  // WordPress shortcode mode - mount each container independently
  shortcodeContainers.forEach((container) => {
    const view = container.getAttribute('data-seamless-view') || 'events';
    const slug = container.getAttribute('data-seamless-slug') || '';
    const siteUrl = container.getAttribute('data-site-url') || window.location.origin;

    createRoot(container).render(
      <StrictMode>
        <App initialView={view} initialSlug={slug} siteUrl={siteUrl} />
      </StrictMode>,
    );
  });
} else {
  // Dev / standalone mode – use the standard #root container
  const containerElement =
    document.getElementById('seamless-react-root') ||
    document.getElementById('events-react-root') ||
    document.getElementById('root');

  if (containerElement) {
    containerElement.id = 'seamless-event-container';
    createRoot(containerElement).render(
      <StrictMode>
        <App />
      </StrictMode>,
    );
  } else {
    console.warn(
      '[Seamless React] No mount point found. Expected a [data-seamless-view] element, ' +
      '#seamless-react-root, #events-react-root, or #root.'
    );
  }
}
