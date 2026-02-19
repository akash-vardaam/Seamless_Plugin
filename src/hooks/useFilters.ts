import { useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import type { Event, FilterState } from '../types/event';

export const DEFAULT_FILTERS: FilterState = {
  search: '',
  status: 'upcoming',
  audience: '',
  focus: '',
  localChapter: '',
  year: '',
};

interface UseFilterStateReturn {
  filters: FilterState;
  updateFilter: (key: keyof FilterState, value: string) => void;
  resetFilters: () => void;
}

export const useFilterState = (): UseFilterStateReturn => {
  const [searchParams, setSearchParams] = useSearchParams();

  // Create filters object from searchParams, falling back to defaults
  const filters: FilterState = useMemo(() => ({
    search: searchParams.get('search') || DEFAULT_FILTERS.search,
    status: (searchParams.get('status') as FilterState['status']) || DEFAULT_FILTERS.status,
    audience: searchParams.get('audience') || DEFAULT_FILTERS.audience,
    focus: searchParams.get('focus') || DEFAULT_FILTERS.focus,
    localChapter: searchParams.get('localChapter') || DEFAULT_FILTERS.localChapter,
    year: searchParams.get('year') || DEFAULT_FILTERS.year,
  }), [searchParams]);

  const updateFilter = (key: keyof FilterState, value: string) => {
    console.log(`Updating filter ${key} to ${value}`);
    setSearchParams(prev => {
      const newParams = new URLSearchParams(prev);
      if (value) {
        newParams.set(key, value);
      } else {
        newParams.delete(key);
      }
      // Reset page to 1 when filters change (common pattern)
      newParams.set('page', '1');
      return newParams;
    });
  };

  const resetFilters = () => {
    setSearchParams(prev => {
      const newParams = new URLSearchParams(prev);
      // Remove all filter keys
      Object.keys(DEFAULT_FILTERS).forEach(key => {
        newParams.delete(key);
      });
      newParams.set('page', '1');
      return newParams;
    });
  };

  return { filters, updateFilter, resetFilters };
};

export const useClientFilters = (events: Event[], filters: FilterState): Event[] => {
  return useMemo(() => {
    return events.filter((event) => {
      const now = new Date();

      // Status filter
      if (filters.status) {
        const startDate = new Date(event.start_date);
        const endDate = new Date(event.end_date);

        if (filters.status === 'upcoming' && startDate <= now) return false;
        if (filters.status === 'current' && (now < startDate || now > endDate)) return false;
        if (filters.status === 'past' && endDate >= now) return false;
      }

      // Search filter (Client side fallback, though server handles it now)
      if (filters.search) {
        const searchLower = filters.search.toLowerCase();
        if (!event.title.toLowerCase().includes(searchLower)) return false;
      }

      // Audience filter
      if (filters.audience) {
        const hasAudience = event.categories?.some((c) => c.id === filters.audience);
        if (!hasAudience) return false;
      }

      // Focus filter
      if (filters.focus) {
        const hasFocus = event.categories?.some((c) => c.id === filters.focus);
        if (!hasFocus) return false;
      }

      // Local Chapter filter
      if (filters.localChapter) {
        const hasChapter = event.categories?.some((c) => c.id === filters.localChapter);
        if (!hasChapter) return false;
      }

      // Year filter
      if (filters.year) {
        const eventYear = new Date(event.start_date).getFullYear().toString();
        if (eventYear !== filters.year) return false;
      }

      return true;
    });
  }, [events, filters]);
};

// We will replace usage in ItemsPage
export const useFilters = (events: Event[]) => {
  const { filters, updateFilter, resetFilters } = useFilterState();
  const filteredItems = useClientFilters(events, filters);
  return { filters, updateFilter, resetFilters, filteredItems, setFilters: () => { } };
};
