import React, { useState, useMemo, useEffect } from 'react';
import { useSegmentedEventPagination } from '../hooks/useSegmentedEventPagination';
import { useCategories } from '../hooks/useCategories';
import { useFilterState, useClientFilters } from '../hooks/useFilters';
import { FilterBar } from './FilterBar';
import { ViewSwitcher } from './ViewSwitcher';
import { Card } from './Card';
import { CalendarView } from './CalendarView';
import { Pagination } from './Pagination';
import type { ViewType, Event } from '../types/event';

const ItemsPage: React.FC = () => {
  // 1. Categories
  const {
    audiences, focuses, localChapters,
    loading: categoriesLoading
  } = useCategories();

  // 2. Filter State
  const { filters, updateFilter, resetFilters } = useFilterState();

  // 3. Current page state (drives backend pagination)
  const [currentPage, setCurrentPage] = useState(1);

  // 4. Data — fetches one API page at a time (per_page=8)
  const {
    events: items, loading: itemsLoading, error, totalPages, totalApiEvents
  } = useSegmentedEventPagination(filters, currentPage);

  // 5. Client-side filtering (Year fallback — though backend pagination might miss events if status/category filters don't cover it)
  const eventsData = (items || []) as Event[];

  // Note: useClientFilters handles "Year" filtering which API cannot do.
  const filteredItems = useClientFilters(eventsData, filters);

  // 6. View state
  const getDefaultView = (): ViewType =>
    typeof window !== 'undefined' && window.innerWidth < 768 ? 'grid' : 'list';
  const [currentView, setCurrentView] = useState<ViewType>(getDefaultView());

  // Responsive view switch
  useEffect(() => {
    const onResize = () => {
      if (window.innerWidth < 768 && currentView === 'list') setCurrentView('grid');
    };
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, [currentView]);

  // Reset to page 1 when any filter changes OR view changes
  useEffect(() => {
    setCurrentPage(1);
  }, [
    filters.search,
    filters.status,
    filters.audience,
    filters.focus,
    filters.localChapter,
    filters.year,
    currentView // Reset on view change
  ]);

  const handleViewChange = (view: ViewType) => {
    setCurrentView(view);
  };

  const handlePageChange = (page: number) => {
    // Clamp page to valid range
    const safePage = Math.max(1, Math.min(page, totalPages));
    setCurrentPage(safePage);
    // Scroll to top of the events container
    document.getElementById('seamless-event-container')?.scrollIntoView({ behavior: 'smooth' });
  };

  // Derive years from loaded data
  const years = useMemo(() => {
    const s = new Set<string>();
    items.forEach(e => {
      if (e.start_date) {
        const y = new Date(e.start_date).getFullYear().toString();
        if (!isNaN(parseInt(y))) s.add(y);
      }
    });
    return Array.from(s).sort((a, b) => parseInt(b) - parseInt(a));
  }, [items]);

  const loading = itemsLoading || categoriesLoading;

  // ── Render ─────────────────────────────────────────────────────
  if (error) {
    return (
      <div id="seamless-event-container" className="seamless-page-wrapper">
        <div className="seamless-error-container">
          <p className="seamless-error-title">Error loading items</p>
          <p className="seamless-error-message">{error}</p>
        </div>
      </div>
    );
  }

  return (
    <div id="seamless-event-container" className="seamless-page-wrapper">
      <div className="seamless-container">
        <div className="seamless-filter-section">
          <FilterBar
            search={filters.search}
            onSearchChange={(v) => updateFilter('search', v)}
            status={filters.status}
            onStatusChange={(v) => updateFilter('status', v as any)}
            audience={filters.audience}
            onAudienceChange={(v) => updateFilter('audience', v)}
            audiences={audiences}
            focus={filters.focus}
            onFocusChange={(v) => updateFilter('focus', v)}
            focuses={focuses}
            localChapter={filters.localChapter}
            onLocalChapterChange={(v) => updateFilter('localChapter', v)}
            localChapters={localChapters}
            year={filters.year}
            onYearChange={(v) => updateFilter('year', v)}
            years={years}
            onReset={resetFilters}
          />
        </div>

        <div className="seamless-results-info">
          <p className="seamless-results-text">
            {loading ? (
              <span>Loading items...</span>
            ) : (
              <>
                Showing{' '}
                <span className="seamless-results-count">{filteredItems.length}</span>{' '}
                item(s)
                {' · '}Page {currentPage} of {totalPages}
              </>
            )}
          </p>
          <ViewSwitcher currentView={currentView} onViewChange={handleViewChange} />
        </div>

        <div className="seamless-items-display" style={{ minHeight: '300px', position: 'relative' }}>
          {loading && (
            <div className="seamless-loading-overlay" style={{
              position: 'absolute', inset: 0,
              display: 'flex', justifyContent: 'center', alignItems: 'center',
              backgroundColor: 'rgba(255,255,255,0.7)', zIndex: 10
            }}>
              <div className="seamless-loading-content">
                <div className="seamless-spinner" />
              </div>
            </div>
          )}

          {!loading && filteredItems.length === 0 ? (
            <div className="seamless-empty-state">
              <p className="seamless-empty-state-text">No items found matching your filters.</p>
            </div>
          ) : !loading && currentView === 'calendar' ? (
            <CalendarView events={filteredItems} />
          ) : !loading && currentView === 'grid' ? (
            <>
              <div className="seamless-items-grid">
                {filteredItems.map(item => (
                  <Card key={item.id} item={item} layout="grid" />
                ))}
              </div>
              <Pagination
                currentPage={currentPage}
                totalPages={totalPages}
                onPageChange={handlePageChange}
                showPageNumbers
              />
            </>
          ) : !loading && (
            <>
              <div className="seamless-items-list">
                {filteredItems.map(item => (
                  <Card key={item.id} item={item} layout="list" />
                ))}
              </div>
              <Pagination
                currentPage={currentPage}
                totalPages={totalPages}
                onPageChange={handlePageChange}
                showPageNumbers
              />
            </>
          )}
        </div>
      </div>
    </div>
  );
};

export default ItemsPage;
