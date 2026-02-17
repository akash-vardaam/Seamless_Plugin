import { useEffect, useState, useCallback, useRef } from 'react';
import axios from 'axios';
import api from '../services/api';
import type { Event, FilterState, PaginationMeta } from '../types/event';

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

            const params = buildParams();
            const cacheKey = `seamless_events_${JSON.stringify(params)}_${uiPage}`;

            // 1. Check Cache
            try {
                const cached = sessionStorage.getItem(cacheKey);
                if (cached) {
                    const data = JSON.parse(cached);
                    setEvents(data.events);
                    setTotalPages(data.totalPages);
                    setTotalApiEvents(data.totalApiEvents);
                    if (data.lastPageRef) {
                        lastPageRef.current = data.lastPageRef;
                    }
                    setLoading(false);
                    return;
                }
            } catch (e) {
                console.log(e);
            }

            try {

                const response = await api.get<any>('/events', {
                    params: { ...params, page: uiPage }
                });

                if (cancelled) return;

                const rawEvents = (response.data.data?.events || []) as Event[];
                const meta = response.data.data?.pagination as PaginationMeta;

                // Update totals directly from API
                const newTotalEvents = meta?.total || 0;
                // Use last_page if available, otherwise calculate
                const newTotalPages = meta?.last_page || Math.ceil(newTotalEvents / PER_PAGE) || 1;

                setTotalApiEvents(newTotalEvents);
                setTotalPages(newTotalPages);

                // Cache the last_page if needed for other refs, though simplified now
                if (meta?.last_page) {
                    lastPageRef.current = meta.last_page;
                }

                // Sorting
                if (mode === 'upcoming') {
                    rawEvents.sort((a, b) => new Date(a.start_date).getTime() - new Date(b.start_date).getTime());
                }
                setEvents(rawEvents);

                // 2. Save to Cache
                try {
                    const dataToCache = {
                        events: rawEvents,
                        totalPages: newTotalPages,
                        totalApiEvents: newTotalEvents,
                        lastPageRef: lastPageRef.current
                    };
                    sessionStorage.setItem(cacheKey, JSON.stringify(dataToCache));
                } catch (e) {
                    console.log(e);
                }

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
