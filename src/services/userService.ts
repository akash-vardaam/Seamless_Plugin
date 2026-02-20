import api from './api';

// Profile Endpoints
export const fetchDashboardProfile = async () => {
    const response = await api.get('/dashboard/profile');
    return response.data;
};

export const updateDashboardProfile = async (data: any) => {
    const response = await api.put('/dashboard/profile/edit', data);
    return response.data;
};

export const changeDashboardPassword = async (data: any) => {
    const response = await api.post('/dashboard/profile/change-password', data);
    return response.data;
};

// Overview
export const fetchDashboardOverview = async () => {
    const response = await api.get('/dashboard/overview');
    return response.data;
};

// Memberships Endpoints
export const fetchDashboardMemberships = async () => {
    const response = await api.get('/dashboard/memberships');
    return response.data;
};

export const fetchDashboardCurrentMembership = async () => {
    const response = await api.get('/dashboard/memberships/current');
    return response.data;
};

export const fetchDashboardMembershipHistory = async () => {
    const response = await api.get('/dashboard/memberships/history');
    return response.data;
};

export const fetchDashboardMembershipPlansAvailable = async () => {
    const response = await api.get('/dashboard/membership-plans/available');
    return response.data;
};

// Orders Endpoints
export const fetchDashboardOrders = async () => {
    const response = await api.get('/dashboard/orders');
    return response.data;
};

// Courses Endpoints
export const fetchDashboardEnrolledCourses = async () => {
    const response = await api.get('/dashboard/courses/enrolled');
    return response.data;
};

export const fetchDashboardIncludedCourses = async () => {
    const response = await api.get('/dashboard/courses/included');
    return response.data;
};
