import { useState, useEffect, useMemo } from 'react';
import api from '../services/api';
import type { Category } from '../types/event';

interface ApiCategory {
    id: string;
    label: string;
    slug: string;
    children: ApiCategory[];
}

interface UseCategoriesReturn {
    audiences: Category[];
    focuses: Category[];
    localChapters: Category[];
    loading: boolean;
    error: string | null;
}

export const useCategories = (): UseCategoriesReturn => {
    const [rawCategories, setRawCategories] = useState<ApiCategory[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const fetchCategories = async () => {
            try {
                setLoading(true);
                // Fetch categories from the new endpoint
                const response = await api.get<any>('/categories');

                console.log('Categories API Response:', response.data);

                let fetchedData: ApiCategory[] = [];

                // Handle response structure { success: true, data: [...] }
                if (response.data && Array.isArray(response.data.data)) {
                    fetchedData = response.data.data;
                } else if (Array.isArray(response.data)) {
                    fetchedData = response.data;
                }

                setRawCategories(fetchedData);
            } catch (err) {
                console.error('Error fetching categories:', err);
                setError('Failed to load categories');
            } finally {
                setLoading(false);
            }
        };

        fetchCategories();
    }, []);

    const { audiences, focuses, localChapters } = useMemo(() => {
        let audienceList: Category[] = [];
        let focusList: Category[] = [];
        let localChapterList: Category[] = [];

        // Helper to convert ApiCategory to application Category
        const mapToCategory = (c: ApiCategory): Category => ({
            id: c.id,
            name: c.label, // Map label to name
            slug: c.slug,
            color: null
        });

        // Iterate through top-level categories to find the main buckets
        rawCategories.forEach((parent) => {
            const slug = parent.slug.toLowerCase();

            if (slug === 'audience' && Array.isArray(parent.children)) {
                audienceList = parent.children.map(mapToCategory);
            } else if (slug === 'focus' && Array.isArray(parent.children)) {
                focusList = parent.children.map(mapToCategory);
            } else if ((slug === 'local-chapters-regions' || slug === 'local-chapter') && Array.isArray(parent.children)) {
                localChapterList = parent.children.map(mapToCategory);
            }
        });

        return {
            audiences: audienceList,
            focuses: focusList,
            localChapters: localChapterList,
        };
    }, [rawCategories]);

    return {
        audiences,
        focuses,
        localChapters,
        loading,
        error
    };
};
