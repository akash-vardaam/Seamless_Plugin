
export interface ContentRules {
    [key: string]: string | number | boolean | null;
}

export interface MembershipPlan {
    id: string;
    label: string;
    sku: string;
    description: string;
    price: number;
    signup_fee: number;
    billing_cycle_display: string;
    period: string;
    period_number: number;
    lifetime_access: boolean;
    is_group_membership: boolean;
    group_seats: number | null;
    content_rules: ContentRules;
}

export interface MembershipPlanResponse {
    success: boolean;
    message: string;
    data: MembershipPlan[];
    pagination: {
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
        from: number;
        to: number;
        has_more_pages: boolean;
    };
    filters_applied: any[];
}
