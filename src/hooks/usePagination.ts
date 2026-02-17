import { useState, useMemo } from 'react';

interface UsePaginationReturn<T> {
  currentPage: number;
  totalPages: number;
  paginatedItems: T[];
  goToPage: (page: number) => void;
  nextPage: () => void;
  prevPage: () => void;
  hasNextPage: boolean;
  hasPrevPage: boolean;
  resetPage: () => void;
}

/**
 * usePagination
 *
 * @param items - The full array of items to paginate
 * @param itemsPerPage - Number of items per UI page (default 6)
 * @param estimatedTotal - Optional total count from the API.
 *   When provided, totalPages is computed from this value instead
 *   of items.length. This keeps the page count stable from the
 *   first API response, even as more data loads progressively.
 */
export const usePagination = <T,>(
  items: T[],
  itemsPerPage: number = 8,
  estimatedTotal?: number
): UsePaginationReturn<T> => {
  const [currentPage, setCurrentPage] = useState(1);

  // Use estimatedTotal (from API pagination.total) when available;
  // otherwise fall back to actual items.length.
  const totalPages = useMemo(() => {
    const count = estimatedTotal != null && estimatedTotal > 0
      ? estimatedTotal
      : items.length;
    return Math.ceil(count / itemsPerPage) || 1;
  }, [estimatedTotal, items.length, itemsPerPage]);

  const paginatedItems = useMemo(() => {
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    return items.slice(startIndex, endIndex);
  }, [items, currentPage, itemsPerPage]);

  const goToPage = (page: number) => {
    const pageNum = Math.max(1, Math.min(page, totalPages));
    setCurrentPage(pageNum);
  };

  const nextPage = () => {
    goToPage(currentPage + 1);
  };

  const prevPage = () => {
    goToPage(currentPage - 1);
  };

  const resetPage = () => {
    setCurrentPage(1);
  };

  return {
    currentPage,
    totalPages,
    paginatedItems,
    goToPage,
    nextPage,
    prevPage,
    hasNextPage: currentPage < totalPages,
    hasPrevPage: currentPage > 1,
    resetPage,
  };
};
