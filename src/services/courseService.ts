import api from './api';
import type { CoursesApiResponse, CourseFilters } from '../types/course';

export const fetchCourses = async (
    page: number = 1,
    filters: CourseFilters
): Promise<CoursesApiResponse> => {
    const params: Record<string, any> = {
        page,
        per_page: 8, // Matching events page default
    };

    if (filters.search) {
        params.search = filters.search;
    }

    if (filters.access_type) {
        params.access_type = filters.access_type;
    }

    if (filters.year) {
        params.year = filters.year;
    }

    // The API does not accept sort parameters, so we do not send them.
    // They will be handled purely in the frontend React hook.

    const response = await api.get<CoursesApiResponse>('/courses', { params });
    return response.data;
};
