
import React, { useMemo, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useSegmentedEventPagination } from '../hooks/useSegmentedEventPagination';
import { useCategories } from '../hooks/useCategories';
import { useFilterState, useClientFilters } from '../hooks/useFilters';
import { FilterBar } from './FilterBar';
import { ViewSwitcher } from './ViewSwitcher';
import { Card } from './Card';
import { CalendarView } from './CalendarView';
import { Pagination } from './Pagination';
import type { ViewType, Event } from '../types/event';
import { useCalendarEvents } from '../hooks/useCalendarEvents';
// ... defaults

export const EventListView: React.FC = () => {
    // 1. Categories
    const {
        audiences, focuses, localChapters,
        loading: categoriesLoading
    } = useCategories();

    // 2. Filter State
    const { filters, updateFilter, resetFilters } = useFilterState();
    const [searchParams, setSearchParams] = useSearchParams();

    // 3. Current page state (drives backend pagination)
    const currentPage = parseInt(searchParams.get('page') || '1');

    // Calendar State
    // Initialize from URL or default to today
    const calendarDate = useMemo(() => {
        const dateParam = searchParams.get('date');
        return dateParam ? new Date(dateParam) : new Date();
    }, [searchParams]);

    // Helper to update URL params
    const updateUrlParams = (updates: Record<string, string | null>) => {
        setSearchParams(prev => {
            const newParams = new URLSearchParams(prev);
            Object.entries(updates).forEach(([key, value]) => {
                if (value === null || (key === 'page' && value === '1')) {
                    newParams.delete(key);
                } else {
                    newParams.set(key, value);
                }
            });
            return newParams;
        });
    };

    // 4. Data — fetches one API page at a time (per_page=8)
    const {
        events: paginatedItems, loading: paginatedLoading, error: paginatedError, totalPages
    } = useSegmentedEventPagination(filters, currentPage);

    // 5. View state
    const getDefaultView = (): ViewType =>
        typeof window !== 'undefined' && window.innerWidth < 768 ? 'grid' : 'list';

    const currentView = (searchParams.get('view') as ViewType) || getDefaultView();

    // 6. Calendar Data - fetches by month
    const {
        events: calendarItems, loading: calendarLoading, error: calendarError
    } = useCalendarEvents(calendarDate, filters, currentView === 'calendar');

    // Determine active data source
    const items = currentView === 'calendar' ? calendarItems : paginatedItems;
    const itemsLoading = currentView === 'calendar' ? calendarLoading : paginatedLoading;
    const error = currentView === 'calendar' ? calendarError : paginatedError;

    // 7. Client-side filtering (Year fallback)
    const eventsData = (items || []) as Event[];

    // Note: useClientFilters handles "Year" filtering which API cannot do.
    const filteredItems = useClientFilters(eventsData, filters);

    // ... Responsive view switch ...
    useEffect(() => {
        const onResize = () => {
            if (window.innerWidth < 768 && currentView === 'list') {
                updateUrlParams({ view: 'grid' });
            }
        };
        window.addEventListener('resize', onResize);
        return () => window.removeEventListener('resize', onResize);
    }, [currentView]);

    const handleViewChange = (view: ViewType) => {
        // Reset page to 1 on view change
        updateUrlParams({ view, page: '1' });
    };

    const handleMonthChange = (date: Date) => {
        // Store simple date string YYYY-MM-DD or just ISO
        updateUrlParams({ date: date.toISOString() });
    };

    const handlePageChange = (page: number) => {
        // Clamp page to valid range
        const safePage = Math.max(1, Math.min(page, totalPages));
        updateUrlParams({ page: safePage.toString() });
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
        <section className="seamless-container">
            <aside className="seamless-filter-section">
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
            </aside>

            <header className="seamless-results-info">
                <p className="seamless-results-text">
                    {loading ? (
                        <span>Loading items...</span>
                    ) : (
                        <>
                            Showing{' '}
                            <span className="seamless-results-count">{filteredItems.length}</span>{' '}
                            item(s)
                            {currentView !== 'calendar' && (
                                <>
                                    {' · '}Page {currentPage} of {totalPages}
                                </>
                            )}
                        </>
                    )}
                </p>
                <ViewSwitcher currentView={currentView} onViewChange={handleViewChange} />
            </header>

            <main className="seamless-items-display" style={{ minHeight: '300px', position: 'relative' }}>
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
                    <CalendarView
                        events={filteredItems}
                        currentDate={calendarDate}
                        onMonthChange={handleMonthChange}
                    />
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
            </main>
        </section>
    );
};
