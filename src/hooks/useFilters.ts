import { useState, useMemo } from 'react';
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
  const [filters, setFilters] = useState<FilterState>(DEFAULT_FILTERS);

  const updateFilter = (key: keyof FilterState, value: string) => {
    console.log(`Updating filter ${key} to ${value}`);
    setFilters((prev) => ({ ...prev, [key]: value }));
  };

  const resetFilters = () => {
    setFilters(DEFAULT_FILTERS);
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

// Deprecated, but keeping for compatibility if referenced elsewhere (unlikely based on my search)
// We will replace usage in ItemsPage
export const useFilters = (events: Event[]) => {
  const { filters, updateFilter, resetFilters } = useFilterState();
  const filteredItems = useClientFilters(events, filters);
  return { filters, updateFilter, resetFilters, filteredItems, setFilters: () => { } };
};
