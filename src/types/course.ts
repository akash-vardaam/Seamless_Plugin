export interface AccessType {
    value: 'free' | 'paid';
    label: string;
}

export interface Course {
    id: number | string;
    title: string;
    slug: string;
    description: string;
    access_type: AccessType;
    price: string | number | null;
    duration_minutes: number;
    image: string | null;
    published_at: string;
    created_at?: string;
    lessons_count?: number; // "lessons" in user description, likely an array or count. Assuming array based on name, but maybe just need count for display? User said "lessons", "membership_plans", "payment_gateways". I'll keep it loose.
    lessons?: any[] | number;
    membership_plans?: any[];
    payment_gateways?: any[];
    // properties for derived display
    formatted_duration?: string;
}

export interface CoursePagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

export interface CourseFilters {
    search: string;
    access_type: 'free' | 'paid' | '';
    sort: 'newest' | 'oldest' | 'title_asc' | 'title_desc';
    year: string;
}

export interface CoursesApiResponse {
    data: Course[];
    pagination: CoursePagination;
    filters_applied: any;
    available_years?: string[];
}
