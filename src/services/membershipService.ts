import api from './api';
import type { MembershipPlanResponse } from '../types/membership';

export const fetchMembershipPlans = async (): Promise<MembershipPlanResponse> => {
    // The base URL config handles tenant changes based on environment or window vars.
    const response = await api.get<MembershipPlanResponse>('/membership-plans');
    return response.data;
};
