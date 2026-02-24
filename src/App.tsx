import React from 'react';
import { BrowserRouter, MemoryRouter, Routes, Route, Navigate } from 'react-router-dom';
import { EventListView } from './components/EventListView';
import { SingleEventPage } from './components/SingleEventPage';
import { MembershipListView } from './components/MembershipView';
import { CoursesView } from './components/CoursesView';
import { UserDashboardView } from './components/UserDashboardView';

interface AppProps {
  /**
   * When mounted by a WordPress shortcode, this tells the app which
   * "page" to render directly (without router-based navigation).
   *   'events'       → EventListView  (shortcode: seamless_events_list)
   *   'single-event' → SingleEventPage (shortcode: seamless_single_event)
   *   'memberships'  → MembershipListView (shortcode: seamless_memberships)
   *   'courses'      → CoursesView     (shortcode: seamless_courses)
   *   'dashboard'    → UserDashboardView (shortcode: seamless_dashboard)
   */
  initialView?: string;
  /** Slug for the single-event view (passed as data attribute by the shortcode) */
  initialSlug?: string;
  /** WordPress site base URL, used for MemoryRouter basename */
  siteUrl?: string;
}

/**
 * Route map – maps `data-seamless-view` values to the component that should render.
 * In WordPress mode each shortcode only ever shows one view, so we render it
 * directly inside a MemoryRouter locked to the matching path.
 *
 * IMPORTANT: All non-event routes use `path: '/'` deliberately.
 * MemoryRouter is isolated per shortcode — it never touches window.location.
 * Using '/' means the initialEntry is always `/?filter=value` (pure query
 * string), so history.replaceState only ever touches the search part of the
 * real browser URL. If we used `path: '/courses'`, the initialEntry would be
 * `/courses?filter=value`, which could cause WordPress to return a 404 if
 * `/courses/` is a reserved permalink or CPT archive.
 */
const VIEW_ROUTES: Record<string, { path: string; element: React.ReactNode }> = {
  events:        { path: '/',  element: <EventListView /> },
  'single-event':{ path: '/events/:slug', element: <SingleEventPage /> },
  memberships:   { path: '/',  element: <MembershipListView /> },
  courses:       { path: '/',  element: <CoursesView /> },
  dashboard:     { path: '/',  element: <UserDashboardView /> },
};

const App: React.FC<AppProps> = ({ initialView, initialSlug, siteUrl: _siteUrl }) => {
  // ── WordPress shortcode mode ────────────────────────────────────────────────
  if (initialView) {
    const route = VIEW_ROUTES[initialView];
    if (!route) {
      return (
        <div id="seamless-plugin-root" className="seamless-page-wrapper">
          <p>Unknown view: {initialView}</p>
        </div>
      );
    }

    // Build the initial entry for MemoryRouter so the route matches immediately.
    let initialEntry = route.path;

    // Read ALL query params from the real browser URL.
    // This makes filter state sharable: opening a URL with ?status=past&audience=xxx
    // will correctly initialise the MemoryRouter with those params so useSearchParams()
    // returns the right values on first render.
    const searchParams = new URLSearchParams(window.location.search);
    const eventParam = searchParams.get('seamless_event');
    const typeParam = searchParams.get('type') || 'events';

    if (initialView === 'events' && eventParam) {
      // Deep-link to a specific event — route group events differently
      if (typeParam === 'group-event') {
        initialEntry = `/group-event/${eventParam}`;
      } else {
        initialEntry = `/events/${eventParam}`;
      }
      // Keep any extra params (they won't affect SingleEventPage but won't break it)
      const qs = searchParams.toString();
      if (qs) initialEntry += `?${qs}`;
    } else if (initialView === 'single-event' && initialSlug) {
      initialEntry = `/events/${initialSlug}`;
    } else {
      // Normal list/dashboard/etc. view — pass through the full query string so that
      // filter params, pagination, and view-mode are all restored from the URL.
      const qs = searchParams.toString();
      if (qs) initialEntry += `?${qs}`;
    }

    return (
      <MemoryRouter initialEntries={[initialEntry]}>
        <div id="seamless-plugin-root" className="seamless-page-wrapper">
          <Routes>
            {/* Primary route for this shortcode */}
            <Route path={route.path} element={route.element} />
            {/*
              Allow internal navigation for single-event group links
              without breaking the other shortcodes.
            */}
            <Route path="/events/:slug" element={<SingleEventPage />} />
            <Route path="/group-event/:slug" element={<SingleEventPage />} />
            {/* Catch-all keeps the app alive if the router drifts */}
            <Route path="*" element={route.element} />
          </Routes>
        </div>
      </MemoryRouter>
    );
  }


  // ── Standalone / development mode ──────────────────────────────────────────
  return (
    <BrowserRouter>
      <div id="seamless-plugin-root" className="seamless-page-wrapper">
        <Routes>
          <Route path="/" element={<EventListView />} />
          <Route path="/events" element={<EventListView />} />
          <Route path="/events/:slug" element={<SingleEventPage />} />
          <Route path="/group-event/:slug" element={<SingleEventPage />} />
          <Route path="/memberships" element={<MembershipListView />} />
          <Route path="/courses" element={<CoursesView />} />
          <Route path="/dashboard" element={<UserDashboardView />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </div>
    </BrowserRouter>
  );
};

export default App;