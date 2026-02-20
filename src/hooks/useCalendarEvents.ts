import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import api from '../services/api';
import type { Event, FilterState } from '../types/event';

function formatError(err: unknown): string {
    if (axios.isAxiosError(err)) {
        const details = err.response
            ? `Status: ${err.response.status} - ${JSON.stringify(err.response.data)}`
            : err.request ? 'No response received' : err.config?.url || '';
        return `${err.message} ${details ? `(${details})` : ''}`;
    }
    return err instanceof Error ? err.message : 'Unknown error';
}

// In-memory cache to avoid repeated sessionStorage access or API calls
const calendarCache = new Map<string, Event[]>();

export const useCalendarEvents = (
    currentDate: Date,
    filters: FilterState,
    enabled: boolean = true
) => {
    const [events, setEvents] = useState<Event[]>([]);
    const [loading, setLoading] = useState(enabled);
    const [error, setError] = useState<string | null>(null);

    // Helper to get start/end of month
    const getMonthRange = (date: Date) => {
        const year = date.getFullYear();
        const month = date.getMonth();
        // Start: 1st of month
        const start = new Date(year, month, 1);
        // End: Last day of month
        const end = new Date(year, month + 1, 0); // 0th day of next month = last day of current

        // Format YYYY-MM-DD
        const formatDate = (d: Date) => {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        };

        return {
            start_date: formatDate(start),
            end_date: formatDate(end)
        };
    };

    const buildParams = useCallback(() => {
        // High limit for calendar view to get "all" events
        const p: any = { per_page: 100 };

        // Apply category filters
        const cats = [filters.audience, filters.focus, filters.localChapter]
            .filter(Boolean).join(',');
        if (cats) p.category_ids = cats;

        if (filters.search) p.search = filters.search;

        // Note: We intentionally ignore filters.status ('upcoming', 'past') 
        // because Calendar view should show events for the specific month regardless of global status preference.
        // Unless user explicitly wants "Past events in March 2026", but usually Calendar overrides "Upcoming/Past".

        // Add date range
        const { start_date, end_date } = getMonthRange(currentDate);
        p.start_date = start_date;
        p.end_date = end_date;

        return p;
    }, [currentDate, filters.audience, filters.focus, filters.localChapter, filters.search]);

    useEffect(() => {
        let cancelled = false;

        const fetchData = async () => {
            if (!enabled) return;

            const params = buildParams();
            const cacheKey = `seamless_calendar_${JSON.stringify(params)}`;

            // Check In-Memory Cache first
            if (calendarCache.has(cacheKey)) {
                setEvents(calendarCache.get(cacheKey)!);
                setLoading(false);
                return;
            }

            setLoading(true);
            setError(null);

            // Check SessionStorage Cache Next
            try {
                const cached = sessionStorage.getItem(cacheKey);
                if (cached) {
                    const data = JSON.parse(cached);
                    calendarCache.set(cacheKey, data); // Pop into memory
                    setEvents(data);
                    setLoading(false);
                    return;
                }
            } catch (e) {
                console.log(e);
            }

            try {
                const response = await api.get<any>('/events', { params });

                if (cancelled) return;

                const rawEvents = (response.data.data?.events || []) as Event[];

                // Save to caches
                calendarCache.set(cacheKey, rawEvents);
                setEvents(rawEvents);

                try {
                    sessionStorage.setItem(cacheKey, JSON.stringify(rawEvents));
                } catch (e) {
                    console.log(e);
                }


            } catch (err) {
                if (!cancelled) {
                    setError(formatError(err));
                    console.error('Calendar fetch error:', err);
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        };

        fetchData();

        return () => { cancelled = true; };
    }, [buildParams, enabled]);

    return { events, loading, error };
};
