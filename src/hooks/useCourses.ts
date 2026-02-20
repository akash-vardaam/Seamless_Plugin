import { useState, useEffect, useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { fetchCourses } from '../services/courseService';
import type { Course, CoursePagination } from '../types/course';

export const useCourses = () => {
    const [searchParams, setSearchParams] = useSearchParams();

    // Parse URL params
    const pageParam = parseInt(searchParams.get('page') || '1', 10);
    const searchParam = searchParams.get('search') || '';
    const accessParam = (searchParams.get('access') as 'free' | 'paid' | '') || '';
    const sortParam = (searchParams.get('sort') as any) || 'newest';
    const yearParam = searchParams.get('year') || '';

    const [courses, setCourses] = useState<Course[]>([]);
    const [pagination, setPagination] = useState<CoursePagination | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Available years from API
    const [availableYears, setAvailableYears] = useState<string[]>([]);

    // Update URL helper
    const updateUrl = useCallback((updates: Record<string, string | null>) => {
        setSearchParams(prev => {
            const newParams = new URLSearchParams(prev);
            Object.entries(updates).forEach(([key, value]) => {
                if (value === null || value === '') {
                    newParams.delete(key);
                } else {
                    newParams.set(key, value);
                }
            });
            return newParams;
        });
    }, [setSearchParams]);

    // Fetch data whenever URL params change
    useEffect(() => {
        const loadCourses = async () => {
            setLoading(true);
            setError(null);

            // Create cache key based on all parameters
            const cacheParams = {
                page: pageParam,
                search: searchParam,
                access: accessParam,
                sort: sortParam,
                year: yearParam
            };
            const cacheKey = `seamless_courses_${JSON.stringify(cacheParams)}`;
            console.log("Cache Key:", cacheKey);

            try {
                // 1. Check Cache first
                const cachedData = sessionStorage.getItem(cacheKey);
                if (cachedData) {
                    try {
                        const parsed = JSON.parse(cachedData);
                        if (parsed && parsed.courses) {
                            console.log("Cache HIT for:", cacheKey);

                            let sortedCachedCourses = [...parsed.courses];
                            switch (sortParam) {
                                case 'oldest':
                                    sortedCachedCourses.sort((a, b) => new Date(a.published_at || a.created_at || '').getTime() - new Date(b.published_at || b.created_at || '').getTime());
                                    break;
                                case 'title_asc':
                                    sortedCachedCourses.sort((a, b) => a.title.localeCompare(b.title));
                                    break;
                                case 'title_desc':
                                    sortedCachedCourses.sort((a, b) => b.title.localeCompare(a.title));
                                    break;
                                case 'newest':
                                default:
                                    sortedCachedCourses.sort((a, b) => new Date(b.published_at || b.created_at || '').getTime() - new Date(a.published_at || a.created_at || '').getTime());
                                    break;
                            }

                            setCourses(sortedCachedCourses);
                            setPagination(parsed.pagination);
                            if (parsed.availableYears) {
                                setAvailableYears(parsed.availableYears);
                            }
                            setLoading(false);
                            return;
                        }
                    } catch (e) {
                        console.warn("Failed to parse cached data", e);
                        sessionStorage.removeItem(cacheKey);
                    }
                }

                console.log("Cache MISS. Fetching API...");

                // 2. Fetch if not cached
                const data = await fetchCourses(pageParam, {
                    search: searchParam,
                    access_type: accessParam,
                    sort: sortParam,
                    year: yearParam
                });

                console.log("API Response:", data);

                const newCourses = data.data || [];
                let newAvailableYears: string[] = [];

                // Determine available years
                if (data.available_years && Array.isArray(data.available_years)) {
                    newAvailableYears = data.available_years;
                } else if (data.filters_applied?.years) {
                    newAvailableYears = data.filters_applied.years;
                } else {
                    // Fallback: Derive years from the courses in the current response
                    const yearsSet = new Set<string>();
                    newCourses.forEach(course => {
                        const dateStr = course.created_at || course.published_at;
                        if (dateStr) {
                            const y = new Date(dateStr).getFullYear().toString();
                            if (!isNaN(parseInt(y))) yearsSet.add(y);
                        }
                    });
                    newAvailableYears = Array.from(yearsSet).sort((a, b) => parseInt(b) - parseInt(a));
                }

                let sortedCourses = [...newCourses];
                switch (sortParam) {
                    case 'oldest':
                        sortedCourses.sort((a, b) => new Date(a.published_at || a.created_at || '').getTime() - new Date(b.published_at || b.created_at || '').getTime());
                        break;
                    case 'title_asc':
                        sortedCourses.sort((a, b) => a.title.localeCompare(b.title));
                        break;
                    case 'title_desc':
                        sortedCourses.sort((a, b) => b.title.localeCompare(a.title));
                        break;
                    case 'newest':
                    default:
                        sortedCourses.sort((a, b) => new Date(b.published_at || b.created_at || '').getTime() - new Date(a.published_at || a.created_at || '').getTime());
                        break;
                }

                setCourses(sortedCourses);
                setPagination(data.pagination);
                setAvailableYears(newAvailableYears);

                // 3. Save to Cache
                try {
                    console.log("Saving to Session Storage:", cacheKey);
                    sessionStorage.setItem(cacheKey, JSON.stringify({
                        courses: newCourses,
                        pagination: data.pagination,
                        availableYears: newAvailableYears
                    }));
                    console.log("Saved successfully.");
                } catch (e) {
                    console.warn("Failed to save to session storage", e);
                }

            } catch (err: any) {
                console.error("Failed to fetch courses", err);
                setError(err.message || 'Failed to load courses.');
            } finally {
                setLoading(false);
            }
        };

        loadCourses();
    }, [pageParam, searchParam, accessParam, sortParam, yearParam]);

    // Handlers
    const handleSearch = (text: string) => {
        updateUrl({ search: text || null, page: '1' });
    };

    const handleAccessFilter = (access: string) => {
        updateUrl({ access: access === 'all' ? null : access, page: '1' });
    };

    const handleSortChange = (sort: string) => {
        updateUrl({ sort, page: '1' });
    };

    const handleYearChange = (year: string) => {
        updateUrl({ year: year === 'all' ? null : year, page: '1' });
    };

    const handlePageChange = (page: number) => {
        updateUrl({ page: page.toString() });
    };

    const resetFilters = useCallback(() => {
        setSearchParams(new URLSearchParams());
    }, [setSearchParams]);

    return {
        courses,
        pagination,
        loading,
        error,
        availableYears,
        filters: {
            search: searchParam,
            access: accessParam,
            sort: sortParam,
            year: yearParam
        },
        updateSearch: handleSearch,
        updateAccess: handleAccessFilter,
        updateSort: handleSortChange,
        updateYear: handleYearChange,
        updatePage: handlePageChange,
        resetFilters
    };
};
