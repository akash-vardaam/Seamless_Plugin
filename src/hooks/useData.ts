import { useSegmentedEventPagination } from './useSegmentedEventPagination';
import type { FilterState, Event } from '../types/event';

export type Item = Event;

export interface UseDataReturn {
  items: Item[];
  loading: boolean;
  error: string | null;
  totalPages: number;
  totalApiEvents: number;
}

/**
 * useData — wrapper around useSegmentedEventPagination.
 *
 * Modified to forward filters and return the list of events
 * for manual pagination.
 */
export const useData = (filters?: FilterState, page: number = 1): UseDataReturn => {
  const {
    events, loading, error, totalPages, totalApiEvents
  } = useSegmentedEventPagination(filters, page);

  return {
    items: events,
    loading,
    error,
    totalPages,
    totalApiEvents
  };
};
