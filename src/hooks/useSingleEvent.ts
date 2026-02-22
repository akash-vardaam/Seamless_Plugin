
import { useState, useEffect } from 'react';
import { fetchEventBySlug, fetchGroupEventBySlug } from '../services/eventService';
import type { Event } from '../types/event';

interface SingleEventState {
    event: Event | null;
    loading: boolean;
    error: string | null;
}

export const useSingleEvent = (slug: string, isGroupEvent: boolean = false) => {
    const [state, setState] = useState<SingleEventState>({
        event: null,
        loading: true,
        error: null,
    });

    useEffect(() => {
        if (!slug) return;

        const fetchEvent = async () => {
            setState(prev => ({ ...prev, loading: true, error: null }));
            try {
                // Try /events/slug first, if that fails we can try /event/slug
                // Based on urlHelper it seems 'event' is the endpoint for single, but 'events' for list.
                // Let's rely on standard REST practices or the helper.
                // urlHelper says `singleEventEndpoint: 'event'`

                // Try to fetch normal event or group event depending on the flag
                const response = isGroupEvent ? await fetchGroupEventBySlug(slug) : await fetchEventBySlug(slug);

                // Check if data is wrapped in 'data'
                const eventData = response.data.data || response.data;

                setState({
                    event: eventData,
                    loading: false,
                    error: null,
                });
            } catch (err: any) {
                setState({
                    event: null,
                    loading: false,
                    error: err.message || 'Failed to fetch event',
                });
            }
        };

        fetchEvent();
    }, [slug, isGroupEvent]);

    return state;
};
