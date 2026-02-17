import { useEffect, useState, useCallback, useRef } from 'react';
import axios from 'axios';
import api from '../services/api';
import type { Event, FilterState, PaginationMeta } from '../types/event';

// ─── Date helpers ───────────────────────────────────────────────
const isUpcoming = (e: Event): boolean => new Date(e.start_date) >= new Date();
const isPast = (e: Event): boolean => new Date(e.start_date) < new Date();

// ─── Error formatter ────────────────────────────────────────────
function formatError(err: unknown): string {
    if (axios.isAxiosError(err)) {
        const details = err.response
            ? `Status: ${err.response.status} - ${JSON.stringify(err.response.data)}`
            : err.request ? 'No response received' : err.config?.url || '';
        return `${err.message} ${details ? `(${details})` : ''}`;
    }
    return err instanceof Error ? err.message : 'Unknown error';
}

// ─── Constants ──────────────────────────────────────────────────
const PER_PAGE = 8;

// ─── Types ──────────────────────────────────────────────────────
interface SegmentedReturn {
    events: Event[];
    loading: boolean;
    error: string | null;
    totalPages: number;
    totalApiEvents: number;
}

// ─── Hook ───────────────────────────────────────────────────────
export const useSegmentedEventPagination = (
    filters?: FilterState,
    uiPage: number = 1
): SegmentedReturn => {
    const [events, setEvents] = useState<Event[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [totalPages, setTotalPages] = useState(1);
    const [totalApiEvents, setTotalApiEvents] = useState(0);

    // Cache last_page for upcoming reverse mapping
    const lastPageRef = useRef<number | null>(null);
    const mode = filters?.status;

    // Use category_ids instead of category, and include status/search
    const buildParams = useCallback(() => {
        const p: any = { per_page: PER_PAGE };
        if (filters) {
            if (filters.status) p.status = filters.status;

            // USE category_ids
            const cats = [filters.audience, filters.focus, filters.localChapter]
                .filter(Boolean).join(',');
            if (cats) p.category_ids = cats;

            if (filters.search) p.search = filters.search;
        }
        return p;
    }, [filters?.status, filters?.audience, filters?.focus, filters?.localChapter, filters?.search]);

    // Reset lastPage cache when filters change
    useEffect(() => {
        lastPageRef.current = null;
    }, [buildParams]);

    useEffect(() => {
        let cancelled = false;

        const fetchData = async () => {
            setLoading(true);
            setError(null);

            try {
                const params = buildParams();
                let apiPage: number;

                if (mode === 'upcoming') {
                    // ── REVERSE mapping: UI page 1 → API last page ──
                    if (lastPageRef.current === null) {
                        const discovery = await api.get<any>('/events', {
                            params: { ...params, page: 1 }
                        });
                        if (cancelled) return;
                        const meta = discovery.data.data?.pagination as PaginationMeta;
                        lastPageRef.current = meta?.last_page || 1;

                        // Set totals from discovery
                        setTotalApiEvents(meta?.total || 0);
                        setTotalPages(Math.ceil((meta?.total || 0) / PER_PAGE) || 1);
                    }
                    apiPage = Math.max(1, lastPageRef.current - uiPage + 1);
                } else {
                    // ── FORWARD: UI page N → API page N ──
                    apiPage = uiPage;
                }

                const response = await api.get<any>('/events', {
                    params: { ...params, page: apiPage }
                });

                if (cancelled) return;

                const rawEvents = (response.data.data?.events || []) as Event[];
                const meta = response.data.data?.pagination as PaginationMeta;

                // Update totals
                setTotalApiEvents(meta?.total || 0);
                setTotalPages(Math.ceil((meta?.total || 0) / PER_PAGE) || 1);

                if (meta?.last_page && mode !== 'upcoming') {
                    // Start or End? For past/default, last_page likely matches API
                    lastPageRef.current = meta.last_page;
                }

                // Sorting
                if (mode === 'upcoming') {
                    // API returns Descending (Apr -> Mar). We want Ascending (Mar -> Apr).
                    // Since we fetched from the end (Reverse Mapping), we just need to reverse the *page* content.
                    rawEvents.sort((a, b) => new Date(a.start_date).getTime() - new Date(b.start_date).getTime());
                } else {
                    // Past: API returns Newest -> Oldest. Keep it.
                }

                setEvents(rawEvents);
            } catch (err) {
                if (!cancelled) {
                    setError(formatError(err));
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        };

        fetchData();
        return () => { cancelled = true; };
    }, [buildParams, mode, uiPage]);

    return { events, loading, error, totalPages, totalApiEvents };
};
