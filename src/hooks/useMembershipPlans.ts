import { useState, useEffect } from 'react';
import { fetchMembershipPlans } from '../services/membershipService';
import type { MembershipPlan } from '../types/membership';

interface UseMembershipPlansReturn {
    plans: MembershipPlan[];
    loading: boolean;
    error: string | null;
}

export const useMembershipPlans = (): UseMembershipPlansReturn => {
    const [plans, setPlans] = useState<MembershipPlan[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const getPlans = async () => {
            try {
                // Using the unified api service which supports dynamic tenant URLs
                const data = await fetchMembershipPlans();
                if (data.success) {
                    setPlans(data.data);
                } else {
                    setError(data.message || 'Failed to fetch membership plans');
                }
            } catch (err: any) {
                setError(err.message || 'An error occurred while fetching plans');
            } finally {
                setLoading(false);
            }
        };

        getPlans();
    }, []);

    return { plans, loading, error };
};
