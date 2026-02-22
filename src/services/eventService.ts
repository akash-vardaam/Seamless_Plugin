import api from './api';

export const fetchCategories = async () => {
    return await api.get<any>('/categories');
};

export const fetchEvents = async (params?: Record<string, any>) => {
    return await api.get<any>('/events', { params });
};

export const fetchGroupEvents = async (params?: Record<string, any>) => {
    return await api.get<any>('/group-events', { params });
};

export const fetchEventBySlug = async (slug: string) => {
    return await api.get<any>(`/events/${slug}`);
};

export const fetchGroupEventBySlug = async (slug: string) => {
    return await api.get<any>(`/group-events/${slug}`);
};
