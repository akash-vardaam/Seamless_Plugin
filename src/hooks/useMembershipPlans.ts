
import { useState, useEffect } from 'react';
import axios from 'axios';
import type { MembershipPlan, MembershipPlanResponse } from '../types/membership';

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
        const fetchPlans = async () => {
            try {
                // Using the specific URL requested by the user
                const response = await axios.get<MembershipPlanResponse>('https://mafp.seamlessams.com/api/membership-plans');
                if (response.data.success) {
                    setPlans(response.data.data);
                } else {
                    setError(response.data.message || 'Failed to fetch membership plans');
                }
            } catch (err: any) {
                setError(err.message || 'An error occurred while fetching plans');
            } finally {
                setLoading(false);
            }
        };

        fetchPlans();
    }, []);

    return { plans, loading, error };
};
