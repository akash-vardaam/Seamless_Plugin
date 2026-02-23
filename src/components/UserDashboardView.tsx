import React, { useState, useEffect } from 'react';
import { LoadingSpinner } from './LoadingSpinner';
import '../styles/user-dashboard.css';
import '../styles/user-dashboard-utils.css';
import '../styles/single-event.css';
import api, { getAccessToken } from '../services/api';
// Type definitions for our dashboard data
interface UserProfile {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    phone_type: string;
    address_line_1: string;
    address_line_2: string;
    city: string;
    state: string;
    zip_code: string;
    country: string;
    [key: string]: any;
}

// Reusable Toast Component
const Toast = ({ message, type, onClose }: { message: string, type: 'success' | 'error', onClose: () => void }) => {
    useEffect(() => {
        const timer = setTimeout(onClose, 4000);
        return () => clearTimeout(timer);
    }, [onClose]);

    return (
        <div className={`seamless-toast-notification seamless-toast-${type}`}>
            {message}
            <button onClick={onClose} className="seamless-toast-close">&times;</button>
        </div>
    );
};

// Reusable Modal Component
const Modal = ({ isOpen, onClose, title, children, className }: { isOpen: boolean, onClose: () => void, title: string, children: React.ReactNode, className?: string }) => {
    if (!isOpen) return null;
    return (
        <div className="seamless-modal-overlay" onClick={onClose}>
            <div className={`seamless-modal-content ${className || ''}`} onClick={e => e.stopPropagation()}>
                <div className="seamless-modal-header">
                    <h3>{title}</h3>
                    <button onClick={onClose} className="seamless-modal-close">&times;</button>
                </div>
                <div className="seamless-modal-body">
                    {children}
                </div>
            </div>
        </div>
    );
};

export const UserDashboardView: React.FC = () => {
    // SSO / Login State
    const [isLoading, setIsLoading] = useState<boolean>(true);

    // Data State
    const [profile, setProfile] = useState<UserProfile | null>(null);
    const [memberships, setMemberships] = useState<any[] | null>(null);
    const [expiredMemberships, setExpiredMemberships] = useState<any[] | null>(null);
    const [courses, setCourses] = useState<any[] | null>(null);
    const [includedCourses, setIncludedCourses] = useState<any[] | null>(null);
    const [courseProgressMap, setCourseProgressMap] = useState<Record<string, any>>({});
    const [orders, setOrders] = useState<any[] | null>(null);
    const [upgradePlans, setUpgradePlans] = useState<any[] | null>(null);
    const [downgradePlans, setDowngradePlans] = useState<any[] | null>(null);

    // UI State
    const [activeView, setActiveView] = useState<'profile' | 'memberships' | 'courses' | 'orders'>('profile');
    const [isEditingProfile, setIsEditingProfile] = useState<boolean>(false);
    const [activeMembershipTab, setActiveMembershipTab] = useState<'active' | 'history'>('active');
    const [activeCourseTab, setActiveCourseTab] = useState<'enrolled' | 'included'>('enrolled');
    const [openDropdownId, setOpenDropdownId] = useState<string | number | null>(null);

    // Action States
    const [actionModal, setActionModal] = useState<'upgrade' | 'downgrade' | 'cancel' | null>(null);
    const [selectedMembershipId, setSelectedMembershipId] = useState<string | null>(null);
    const [selectedPlanForSwap, setSelectedPlanForSwap] = useState<any | null>(null);
    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [toast, setToast] = useState<{ type: 'success' | 'error', message: string } | null>(null);


    const getApiBase = (): string => {
        const cfg = (window as any).seamlessReactConfig;
        if (cfg?.clientDomain) return cfg.clientDomain + '/api';
        return '/api';
    };

    // Delegate token resolution to the shared api.ts helper (keeps logic in one place)
    const getAuthToken = (): string => getAccessToken();

    const getClientDomain = (): string => {
        const cfg = (window as any).seamlessReactConfig;
        if (cfg?.clientDomain) return cfg.clientDomain;
        return '';
    };

    const fetchApiEndpoint = async (endpoint: string, stateSetter: React.Dispatch<React.SetStateAction<any>>, isArray: boolean = true, method: string = 'GET', bodyPayload?: any) => {
        setIsLoading(true);
        try {
            const token = getAuthToken();
            const config: import('axios').AxiosRequestConfig = {
                method,
                url: endpoint,
                headers: {
                    ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                },
                ...(bodyPayload && Object.keys(bodyPayload).length > 0 && { data: bodyPayload })
            };
            const response = await api.request(config);
            const data = response.data;

            if (data && data.message && !data.data) {
                stateSetter(isArray ? [] : null);
                return;
            }

            const parsedData = data?.data || data;

            stateSetter((prev: any) => {
                if (!isArray && prev && typeof prev === 'object') {
                    return { ...prev, ...parsedData };
                }
                if (isArray) {
                    if (Array.isArray(parsedData)) return parsedData;
                    if (parsedData && Array.isArray(parsedData.data)) return parsedData.data;
                    return [];
                }
                return parsedData;
            });
        } catch (err: any) {
            console.error(`[Dashboard] Failed to fetch API data for ${endpoint}:`, err);
            stateSetter((prev: any) => prev || (isArray ? [] : null));
        } finally {
            setIsLoading(false);
        }
    };


    useEffect(() => {
        let savedView = localStorage.getItem('seamless-user-dashboard-active-view-react');
        if (savedView && ['profile', 'memberships', 'courses', 'orders'].includes(savedView)) {
            setActiveView(savedView as any);
        } else {
            savedView = 'profile';
        }
        // Always fetch the quick sidebar info using GET
        if (!profile) fetchApiEndpoint('/dashboard/profile', setProfile, false);
    }, []);

    useEffect(() => {
        if (activeView === 'profile') {
            // Then fetch the extended profile data using PUT when on the profile page
            fetchApiEndpoint('/dashboard/profile/edit', setProfile, false, 'PUT', {});
        } else if (activeView === 'orders' && orders === null) {
            fetchApiEndpoint('/dashboard/orders', setOrders);
        } else if (activeView === 'courses') {
            if (courses === null) fetchApiEndpoint('/dashboard/courses/enrolled', setCourses);
            if (includedCourses === null) fetchApiEndpoint('/dashboard/courses/included', setIncludedCourses);
        } else if (activeView === 'memberships') {
            if (activeMembershipTab === 'active' && memberships === null) {
                fetchApiEndpoint('/dashboard/memberships', setMemberships);
                fetchApiEndpoint('/dashboard/membership-plans/upgrades', setUpgradePlans);
                fetchApiEndpoint('/dashboard/membership-plans/downgrades', setDowngradePlans);
            } else if (activeMembershipTab === 'history' && expiredMemberships === null) {
                fetchApiEndpoint('/dashboard/memberships/history', setExpiredMemberships);
            }
        }
    }, [activeView, activeCourseTab, activeMembershipTab]);

    useEffect(() => {
        // Fetch progress for any loaded course that doesn't have progress yet
        const fetchProgress = async (c: any) => {
            if (!c?.id || courseProgressMap[c.id]) return;
            try {
                const token = getAuthToken();
                const res = await fetch(`${getApiBase()}/dashboard/courses/${c.id}/progress`, {
                    credentials: 'omit',
                    headers: {
                        ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
                        'Accept': 'application/json'
                    }
                });
                const data = await res.json();
                if (data && data.success) {
                    setCourseProgressMap(prev => ({ ...prev, [c.id]: data.data || {} }));
                }
            } catch (err) {
                console.error(`Status check failed for ${c.id}`);
            }
        };

        if (courses && Array.isArray(courses)) courses.forEach(fetchProgress);
        if (includedCourses && Array.isArray(includedCourses)) includedCourses.forEach(fetchProgress);

    }, [courses, includedCourses]);


    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            const target = event?.target as Element;
            if (!target.closest('.seamless-user-dashboard-menu-container')) {
                setOpenDropdownId(null);
            }
        };
        if (openDropdownId !== null) document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [openDropdownId]);

    const switchView = (view: 'profile' | 'memberships' | 'courses' | 'orders') => {
        setActiveView(view);
        localStorage.setItem('seamless-user-dashboard-active-view-react', view);
        setIsEditingProfile(false);
    };

    // --- Actions ---

    const handleProfileSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        try {
            const payload = {
                first_name: profile?.first_name || '',
                last_name: profile?.last_name || '',
                email: profile?.email || '',
                phone: profile?.phone || '',
                phone_type: profile?.phone_type || 'mobile',
                address_line_1: profile?.address_line_1 || '',
                address_line_2: profile?.address_line_2 || '',
                city: profile?.city || '',
                state: profile?.state || '',
                zip_code: profile?.zip_code || '',
                country: profile?.country || ''
            };

            const token = getAuthToken();
            const response = await fetch(`${getApiBase()}/dashboard/profile/edit`, {
                method: 'PUT',
                credentials: 'omit',
                headers: {
                    ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            if (!response.ok) throw new Error("Failed to save profile.");
            setToast({ type: 'success', message: 'Profile updated successfully!' });
            setIsEditingProfile(false);
            fetchApiEndpoint('/dashboard/profile/edit', setProfile, false, 'PUT', {});
        } catch (error) {
            setToast({ type: 'error', message: 'Could not update profile.' });
        } finally {
            setIsSubmitting(false);
        }
    };


    const handleProfileChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        if (!profile) return;
        setProfile({ ...profile, [e.target.name]: e.target.value });
    };

    const triggerSystemAction = async (endpoint: string, method: string = 'POST', payload: any = {}, successMsg: string = 'Action successful.') => {
        setIsSubmitting(true);
        try {
            const token = getAuthToken();
            const config: import('axios').AxiosRequestConfig = {
                method,
                url: endpoint,
                headers: {
                    ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                },
                data: payload
            };
            const response = await api.request(config);
            const data = response.data;
            if (data?.success || response.status === 200) {
                setToast({ type: 'success', message: successMsg });
                // force reload to get updated status
                fetchApiEndpoint('/dashboard/memberships', setMemberships);
                fetchApiEndpoint('/dashboard/memberships/history', setExpiredMemberships);
            } else {
                setToast({ type: 'error', message: data?.message || 'Failed to apply action.' });
            }
        } catch (err: any) {
            setToast({ type: 'error', message: err?.response?.data?.message || 'An error occurred during action.' });
        } finally {
            setIsSubmitting(false);
            const modal = document.getElementById('seamless-action-modal') as any;
            if (modal) modal.close();
            setSelectedPlanForSwap(null);
        }
    };

    const handleCancelMembership = () => {
        triggerSystemAction(`/dashboard/memberships/cancel/${selectedMembershipId}`, 'POST', { key: 'membership' }, 'Membership successfully canceled.');
    };

    const handlePlanSwap = () => {
        if (!selectedPlanForSwap || !actionModal || actionModal === 'cancel') return;
        triggerSystemAction(`/dashboard/memberships/${actionModal}/${selectedPlanForSwap.id}`, 'POST', {}, `Successfully applied membership ${actionModal}!`);
    };

    const openModalFor = (type: 'upgrade' | 'downgrade' | 'cancel', id: string) => {
        setSelectedMembershipId(id);
        setActionModal(type);
        setSelectedPlanForSwap(null);
        setOpenDropdownId(null);
    };

    // --- Renderers ---

    const renderPlanList = (plans: any[] | null, type: 'upgrade' | 'downgrade') => {
        if (!plans || plans.length === 0) {
            return <div className="seamless-empty-card">No {type} options currently available.</div>;
        }

        const sp = selectedPlanForSwap;
        const currentMem = memberships?.find(m => m.id === selectedMembershipId);

        let proration = null;
        if (sp && currentMem) {
            const remainingDays = Math.max(0, Math.ceil(parseFloat(currentMem.remaining_days || 0)));
            const currentPrice = parseFloat(currentMem.plan?.price || 0);
            const newPrice = parseFloat(sp.price || 0);

            // Dynamically calculate roughly equivalent daily rates based on period
            const getDays = (p: string, num: number) => {
                if (p === 'year') return 365 * (num || 1);
                if (p === 'week') return 7 * (num || 1);
                if (p === 'day') return 1 * (num || 1);
                return 30 * (num || 1);
            };

            const currentDailyRate = currentPrice / getDays(currentMem.plan?.period || 'month', currentMem.plan?.period_number || 1);
            const newDailyRate = newPrice / getDays(sp.period || 'month', sp.period_number || 1);

            const currentPlanCredit = currentDailyRate * remainingDays;

            const isRefundMode = type === 'downgrade';

            // For upgrades, we charge the full new plan price minus credit from current.
            // For downgrades, we use the prorated difference of remaining days.
            const newPlanCharge = isRefundMode ? (newDailyRate * remainingDays) : newPrice;
            const diff = newPlanCharge - currentPlanCredit;

            proration = {
                days: remainingDays,
                charge: newPlanCharge.toFixed(2),
                credit: currentPlanCredit.toFixed(2),
                total: Math.abs(diff).toFixed(2),
                isRefund: isRefundMode
            };
        }

        return (
            <div className="seamless-modal-body-split">
                {/* LEFT COLUMN: Plans List + Pricing Breakdown */}
                <div>
                    <h4 className="seamless-modal-subheader">Available Plans</h4>
                    <div className="seamless-plan-list">
                        {plans.map(plan => (
                            <div
                                key={plan.id}
                                className={`seamless-plan-select-item ${sp?.id === plan.id ? 'selected' : ''}`}
                                onClick={() => setSelectedPlanForSwap(plan)}
                            >
                                <span className="seamless-plan-select-name">{plan.label}</span>
                                <span className="seamless-plan-select-price">${plan.price}<span>/{plan.period}</span></span>
                            </div>
                        ))}
                    </div>

                    {sp && proration && (
                        <div className="seamless-pricing-breakdown">
                            <h4 className="seamless-modal-subheader seamless-pricing-breakdown-hdr">Pricing Breakdown</h4>

                            <div className="seamless-pricing-row">
                                <span>New Plan Charge:</span>
                                <span>{proration.isRefund ? '-' : ''}${proration.charge}</span>
                            </div>
                            <div className="seamless-pricing-row">
                                <span>Current Plan Credit:</span>
                                <span>{proration.isRefund ? '' : '-'}${proration.credit}</span>
                            </div>

                            <div className="seamless-pricing-row total">
                                <span>{type === 'upgrade' ? 'Estimated Additional Cost:' : 'Estimated Refund/Credit:'}</span>
                                <span className={`seamless-pricing-diff ${type === "upgrade" ? "seamless-color-upgrade" : "seamless-color-downgrade"}`}>
                                    ${proration.total}
                                </span>
                            </div>
                            <p className="seamless-pricing-prorated-text">Prorated for <strong>{proration.days}</strong> remaining days</p>
                        </div>
                    )}
                </div>

                {/* RIGHT COLUMN: Offerings */}
                <div>
                    <h4 className="seamless-modal-subheader">{sp ? `${sp.label} - Offerings` : 'Offerings'}</h4>
                    <div className={`seamless-offerings-box ${sp ? "seamless-bg-offerings-active" : "seamless-bg-offerings-inactive"}`}>
                        {sp ? (
                            sp.content_rules && Object.keys(sp.content_rules).length > 0 ? (
                                <ul className="seamless-offerings-list">
                                    {Object.entries(sp.content_rules).map(([key, val]: any) => (
                                        <li key={key}><strong>{key}</strong>: {val}</li>
                                    ))}
                                </ul>
                            ) : (
                                <span className="seamless-offerings-empty">No offerings listed for this plan</span>
                            )
                        ) : (
                            <span className="seamless-offerings-empty">Select a plan to view its offerings</span>
                        )}
                    </div>
                </div>

                {/* Sticky Footer Area in body */}
                <div className="seamless-modal-footer seamless-col-span-full seamless-modal-footer">
                    <button type="button" onClick={() => setActionModal(null)} className="seamless-user-dashboard-btn-secondary">Cancel</button>
                    <button
                        type="button"
                        disabled={isSubmitting || !sp}
                        onClick={handlePlanSwap}
                        className="seamless-user-dashboard-btn-primary"
                    >
                        {isSubmitting ? 'Processing...' : `Confirm ${type === 'upgrade' ? 'Upgrade' : 'Downgrade'}`}
                    </button>
                </div>
            </div>
        );
    };

    if (isLoading && !profile) {
        return <LoadingSpinner />;
    }

    return (
        <div className="seamless-user-dashboard-section">
            {toast && <Toast message={toast.message} type={toast.type} onClose={() => setToast(null)} />}

            {/* Modals */}
            <Modal isOpen={actionModal === 'upgrade'} onClose={() => setActionModal(null)} title="Upgrade Membership" className="seamless-modal-lg">
                {renderPlanList(upgradePlans, 'upgrade')}
            </Modal>

            <Modal isOpen={actionModal === 'downgrade'} onClose={() => setActionModal(null)} title="Downgrade Membership" className="seamless-modal-lg">
                {renderPlanList(downgradePlans, 'downgrade')}
            </Modal>

            <Modal isOpen={actionModal === 'cancel'} onClose={() => setActionModal(null)} title="Cancel Membership">
                <div className="seamless-cancel-modal-body">
                    <div className="seamless-cancel-modal-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" /></svg>
                    </div>
                    <h4 className="seamless-cancel-modal-title">Are you sure you want to cancel?</h4>
                    <p className="seamless-cancel-modal-text">
                        You will immediately terminate the auto-renewal on your billing portal. You may still retain access through the end of your current billing or grace period.
                    </p>
                    <div className="seamless-cancel-actions">
                        <button onClick={() => setActionModal(null)} className="seamless-btn-keep">Keep Membership</button>
                        <button disabled={isSubmitting} onClick={handleCancelMembership} className="seamless-btn-cancel-now">
                            {isSubmitting ? 'Canceling...' : 'Yes, Cancel Now'}
                        </button>
                    </div>
                </div>
            </Modal>

            <div className="seamless-user-dashboard seamless-user-dashboard-react-layout" data-widget-id="react">
                <aside className="seamless-user-dashboard-sidebar">
                    <div className="seamless-user-dashboard-profile-card">
                        <div className="seamless-profile-avatar">
                            {(profile?.first_name?.charAt(0) || '')}{(profile?.last_name?.charAt(0) || '')}
                        </div>
                        <div className="seamless-user-dashboard-profile-name">{profile?.first_name} {profile?.last_name}</div>
                        <div className="seamless-user-dashboard-profile-email">{profile?.email}</div>
                    </div>

                    <nav className="seamless-user-dashboard-nav">
                        {['profile', 'memberships', 'courses', 'orders'].map(view => (
                            <a
                                key={view}
                                href={`#${view}`}
                                className={`seamless-user-dashboard-nav-item seamless-nav-link ${activeView === view ? 'active' : ''} seamless-no-underline`}
                                onClick={(e) => {
                                    e.preventDefault();
                                    switchView(view as any);
                                }}
                            >
                                <span>{view}</span>
                            </a>
                        ))}
                        <a
                            href={(window as any).seamless_logout_url || `/wp-login.php?action=logout&redirect_to=${encodeURIComponent(window.location.origin + '/dashboard')}`}
                            className="seamless-user-dashboard-nav-item seamless-user-dashboard-nav-logout seamless-nav-link seamless-no-underline"
                            style={{ background: 'none', border: 'none', cursor: 'pointer', padding: 0, textAlign: 'left', width: '100%' }}
                        >
                            <span>Logout</span>
                        </a>
                    </nav>
                </aside>

                <main className="seamless-user-dashboard-main">

                    {/* PROFILE VIEW */}
                    {activeView === 'profile' && (
                        <div className="seamless-user-dashboard-view active">
                            <div className="seamless-dashboard-content-container">
                                <div className="seamless-profile-content-card">
                                    <div className="seamless-profile-header">
                                        <h3>Profile Information</h3>
                                        {!isEditingProfile && (
                                            <button className="seamless-edit-btn" onClick={() => setIsEditingProfile(true)}>
                                                Edit Profile
                                            </button>
                                        )}
                                    </div>

                                    {!isEditingProfile ? (
                                        <div className="seamless-user-dashboard-profile-view-mode">
                                            <div className="seamless-grid-2-col">
                                                <div><label className="seamless-value-label">First Name</label><div className="seamless-value-text">{profile?.first_name || '—'}</div></div>
                                                <div><label className="seamless-value-label">Last Name</label><div className="seamless-value-text">{profile?.last_name || '—'}</div></div>
                                                <div><label className="seamless-value-label">Email</label><div className="seamless-value-text">{profile?.email || '—'}</div></div>
                                                <div><label className="seamless-value-label">Phone</label><div className="seamless-value-text">{profile?.phone || '—'} {profile?.phone_type ? `(${profile.phone_type})` : ''}</div></div>
                                            </div>

                                            <h4 className="seamless-section-title">Address Details</h4>
                                            <div className="seamless-grid-2-col">
                                                <div className="seamless-grid-span-2"><label className="seamless-value-label">Address Line 1</label><div className="seamless-value-text">{profile?.address_line_1 || '—'}</div></div>
                                                <div className="seamless-grid-span-2"><label className="seamless-value-label">Address Line 2</label><div className="seamless-value-text">{profile?.address_line_2 || '—'}</div></div>
                                                <div><label className="seamless-value-label">City</label><div className="seamless-value-text">{profile?.city || '—'}</div></div>
                                                <div><label className="seamless-value-label">State</label><div className="seamless-value-text">{profile?.state || '—'}</div></div>
                                                <div><label className="seamless-value-label">Zip Code</label><div className="seamless-value-text">{profile?.zip_code || '—'}</div></div>
                                                <div><label className="seamless-value-label">Country</label><div className="seamless-value-text">{profile?.country || '—'}</div></div>
                                            </div>
                                        </div>
                                    ) : (
                                        <form onSubmit={handleProfileSubmit}>
                                            <div className="seamless-grid-2-col">
                                                <div><label className="seamless-user-dashboard-label-text">First Name</label><input required name="first_name" value={profile?.first_name || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" /></div>
                                                <div><label className="seamless-user-dashboard-label-text">Last Name</label><input required name="last_name" value={profile?.last_name || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" /></div>
                                                <div className="seamless-grid-span-2"><label className="seamless-user-dashboard-label-text">Email</label><input required type="email" name="email" value={profile?.email || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" /></div>
                                                <div><label className="seamless-user-dashboard-label-text">Phone</label><input name="phone" value={profile?.phone || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" /></div>
                                                <div>
                                                    <label className="seamless-user-dashboard-label-text">Phone Type</label>
                                                    <select name="phone_type" value={profile?.phone_type || 'mobile'} onChange={handleProfileChange} className="seamless-user-dashboard-input">
                                                        <option value="mobile">Mobile</option><option value="home">Home</option><option value="work">Work</option><option value="">Other</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <h4 className="seamless-section-title">Address Details</h4>
                                            <div className="seamless-grid-2-col">
                                                <div className="seamless-grid-span-2"><label className="seamless-user-dashboard-label-text">Address Line 1</label><input name="address_line_1" value={profile?.address_line_1 || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" /></div>
                                                <div className="seamless-grid-span-2"><label className="seamless-user-dashboard-label-text">Address Line 2</label><input name="address_line_2" value={profile?.address_line_2 || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" /></div>
                                                <div><label className="seamless-user-dashboard-label-text">City</label><input name="city" value={profile?.city || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" /></div>
                                                <div><label className="seamless-user-dashboard-label-text">State</label><input name="state" value={profile?.state || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" /></div>
                                                <div><label className="seamless-user-dashboard-label-text">Zip</label><input name="zip_code" value={profile?.zip_code || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" /></div>
                                                <div><label className="seamless-user-dashboard-label-text">Country</label><input name="country" value={profile?.country || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" /></div>
                                            </div>

                                            <div className="seamless-form-actions">
                                                <button type="submit" disabled={isSubmitting} className="seamless-user-dashboard-btn-primary">{isSubmitting ? 'Saving...' : 'Save Profile'}</button>
                                                <button type="button" onClick={() => setIsEditingProfile(false)} disabled={isSubmitting} className="seamless-user-dashboard-btn-secondary">Cancel</button>
                                            </div>
                                        </form>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* MEMBERSHIPS VIEW */}
                    {activeView === 'memberships' && (
                        <div className="seamless-user-dashboard-view active">
                            <div className="seamless-dashboard-content-container">

                                {/* TOP SUMMARY SECTION */}
                                <div className="seamless-summary-row seamless-mb-32">
                                    <div className="seamless-count-card">
                                        <h2 className="seamless-count-card-number">{memberships?.length || 0}</h2>
                                        <p className="seamless-count-card-label">Total Active Memberships</p>
                                    </div>

                                    <div className="seamless-flex-col-gap-24">
                                        {memberships && memberships.length > 0 ? (
                                            <div className="seamless-blue-card seamless-padding-24-full-center">
                                                <div className="seamless-blue-card-content seamless-justify-center">
                                                    <h3 className="seamless-blue-card-title">{memberships[0].plan?.label || memberships[0].title || memberships[0].name || 'Membership'}</h3>
                                                    <p className="seamless-blue-card-expiry seamless-mt-8">
                                                        Expires on: <strong>{memberships[0].expiry_date ? new Date(memberships[0].expiry_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A'}</strong>
                                                    </p>
                                                </div>
                                                <div className="seamless-blue-badge seamless-capitalize">{(memberships[0].status || 'Active')}</div>
                                            </div>
                                        ) : (
                                            <div className="seamless-blue-card seamless-opacity-bg-card">
                                                <div className="seamless-blue-card-content seamless-justify-center">
                                                    <h3 className="seamless-blue-card-title">No Active Membership</h3>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <div className="seamless-user-dashboard-tabs-wrapper">
                                    <div className="seamless-user-dashboard-tabs-header">
                                        <button className={`seamless-user-dashboard-tab ${activeMembershipTab === 'active' ? 'active' : ''}`} onClick={() => setActiveMembershipTab('active')}>
                                            Active Memberships <span className="seamless-tab-count">{memberships?.length || 0}</span>
                                        </button>
                                        <button className={`seamless-user-dashboard-tab ${activeMembershipTab === 'history' ? 'active' : ''}`} onClick={() => setActiveMembershipTab('history')}>
                                            Expired Memberships <span className="seamless-tab-count">{expiredMemberships?.length || 0}</span>
                                        </button>
                                    </div>

                                    <div className="seamless-user-dashboard-tab-content active seamless-transparent-mt-24">
                                        {activeMembershipTab === 'active' ? (
                                            !memberships || memberships.length === 0 ? (
                                                <div className="seamless-empty-card">
                                                    <p>You do not have any active memberships.</p>
                                                </div>
                                            ) : (
                                                <div className="seamless-grid-gap-16">
                                                    {memberships.map((mem) => {
                                                        const hUp = upgradePlans && upgradePlans.length > 0;
                                                        const hDown = downgradePlans && downgradePlans.length > 0;
                                                        return (
                                                            <div key={mem.id} className="seamless-membership-card-box seamless-items-start">
                                                                <div className="seamless-flex-col-gap-16-full">
                                                                    <div className="seamless-flex-between-center">
                                                                        <h3 className="seamless-card-title">{mem.plan?.label || mem.title || mem.name || 'Membership'}</h3>

                                                                        {/* ACTIONS (Three dots menu) */}
                                                                        <div className={`seamless-user-dashboard-menu-container ${openDropdownId === mem.id ? "active" : ""} seamless-position-relative`}>
                                                                            <button onClick={() => setOpenDropdownId(openDropdownId === mem.id ? null : mem.id)} className="seamless-btn-transparent">
                                                                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="1.5" /><circle cx="12" cy="5" r="1.5" /><circle cx="12" cy="19" r="1.5" /></svg>
                                                                            </button>

                                                                            <div className="seamless-user-dashboard-menu-dropdown seamless-menu-dropdown-styled seamless-menu-dropdown-pos">
                                                                                {hUp && <button onClick={() => openModalFor('upgrade', mem.id)} className="seamless-menu-item-upgrade">Upgrade Plan</button>}
                                                                                {hDown && <button onClick={() => openModalFor('downgrade', mem.id)} className="seamless-menu-item-downgrade">Downgrade Plan</button>}
                                                                                {(hUp || hDown) && <div className="seamless-menu-divider" />}
                                                                                <button onClick={() => openModalFor('cancel', mem.id)} className="seamless-menu-item-cancel">Cancel Membership</button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div className="seamless-flex-gap-24-slate">
                                                                        <span>Purchased: {mem.created_at ? new Date(mem.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A'}</span>
                                                                        <span>Expires: {mem.expiry_date ? new Date(mem.expiry_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A'}</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            )
                                        ) : (
                                            /* HISTORY */
                                            !expiredMemberships || expiredMemberships.length === 0 ? (
                                                <div className="seamless-empty-card">
                                                    <p>No past membership history available.</p>
                                                </div>
                                            ) : (
                                                <div className="seamless-grid-gap-16">
                                                    {expiredMemberships.map((mem) => (
                                                        <div key={mem.id} className="seamless-membership-card-box seamless-items-center">
                                                            <div className="seamless-flex-col-gap-16-full">
                                                                <h3 className="seamless-card-title">{mem.plan?.label || mem.title || mem.name || 'Membership'}</h3>
                                                                <div className="seamless-flex-gap-24-slate">
                                                                    <span>Purchased: {mem.created_at ? new Date(mem.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A'}</span>
                                                                    <span>Expired: {mem.expiry_date ? new Date(mem.expiry_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'}</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* COURSES VIEW */}
                    {activeView === 'courses' && (
                        <div className="seamless-user-dashboard-view active">
                            <div className="seamless-dashboard-content-container">
                                <div className="seamless-course-summary-grid">
                                    <div className="seamless-course-stat-card">
                                        <div className="seamless-course-stat-number">{(courses?.length || 0) + (includedCourses?.length || 0)}</div>
                                        <div className="seamless-course-stat-label">Total Courses</div>
                                    </div>
                                    <div className="seamless-course-stat-card">
                                        <div className="seamless-course-stat-number">{Object.values(courseProgressMap).filter(p => p.progress === 100).length}</div>
                                        <div className="seamless-course-stat-label">Completed</div>
                                    </div>
                                    <div className="seamless-course-progress-card">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z" /><path d="M2 17l10 5 10-5" /><path d="M2 12l10 5 10-5" /></svg>
                                        No courses in progress
                                    </div>
                                </div>
                                <div className="seamless-user-dashboard-tabs-wrapper">
                                    <div className="seamless-user-dashboard-tabs-header">
                                        <button className={`seamless-user-dashboard-tab ${activeCourseTab === 'enrolled' ? 'active' : ''}`} onClick={() => setActiveCourseTab('enrolled')}>
                                            Enrolled Courses <span className="seamless-tab-count">{courses?.length || 0}</span>
                                        </button>
                                        <button className={`seamless-user-dashboard-tab ${activeCourseTab === 'included' ? 'active' : ''}`} onClick={() => setActiveCourseTab('included')}>
                                            Included in Membership <span className="seamless-tab-count">{includedCourses?.length || 0}</span>
                                        </button>
                                    </div>
                                    <div className="seamless-user-dashboard-tab-content active seamless-transparent-mt-24">
                                        {activeCourseTab === 'enrolled' && (
                                            !courses || courses.length === 0 ? (
                                                <div className="seamless-empty-card"><p>You have not enrolled in any courses yet.</p></div>
                                            ) : (
                                                <div className="seamless-course-grid">
                                                    {courses.map((course: any, i: number) => {
                                                        const p = courseProgressMap[course?.id];
                                                        return (
                                                            <div key={course?.id || i} className="seamless-course-card-n">
                                                                <div className="seamless-course-card-img-wrapper">
                                                                    <img src={course?.image || 'https://via.placeholder.com/400x200?text=Course'} alt={course?.title} />
                                                                </div>
                                                                <div className="seamless-course-card-body">
                                                                    <h4 className="seamless-course-card-title">{course?.title || course?.name}</h4>
                                                                    <div className="seamless-course-card-meta">
                                                                        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /></svg> {p?.total_lessons || course?.lessons_count || 0} lessons</span>
                                                                        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" /></svg> {course?.duration_minutes || 0} minutes</span>
                                                                    </div>
                                                                    <a href={`${getClientDomain()}/courses/${course?.slug || course?.id}`} className="seamless-course-card-action">
                                                                        Start Course <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M5 12h14" /><path d="M12 5l7 7-7 7" /></svg>
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            )
                                        )}
                                        {activeCourseTab === 'included' && (
                                            !includedCourses || includedCourses.length === 0 ? (
                                                <div className="seamless-empty-card"><p>You do not have any courses included in your membership.</p></div>
                                            ) : (
                                                <div className="seamless-course-grid">
                                                    {includedCourses.map((course: any, i: number) => {
                                                        const p = courseProgressMap[course?.id];
                                                        return (
                                                            <div key={course?.id || i} className="seamless-course-card-n">
                                                                <div className="seamless-course-card-img-wrapper">
                                                                    <img src={course?.image || 'https://via.placeholder.com/400x200?text=Course'} alt={course?.title} />
                                                                </div>
                                                                <div className="seamless-course-card-body">
                                                                    <h4 className="seamless-course-card-title">{course?.title || course?.name}</h4>
                                                                    <div className="seamless-course-card-meta">
                                                                        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" /><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" /></svg> {p?.total_lessons || course?.lessons_count || 0} lessons</span>
                                                                        <span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" /></svg> {course?.duration_minutes || 0} minutes</span>
                                                                    </div>
                                                                    <a href={`${getClientDomain()}/courses/${course?.slug || course?.id}`} className="seamless-course-card-action">
                                                                        Start Course <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M5 12h14" /><path d="M12 5l7 7-7 7" /></svg>
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            )
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* ORDERS VIEW */}
                    {activeView === 'orders' && (
                        <div className="seamless-user-dashboard-view active">
                            <div className="seamless-dashboard-content-container">
                                <h3 className="seamless-user-dashboard-view-title seamless-mt-0">Order History</h3>
                                <div className="seamless-table-wrapper seamless-mt-24">
                                    <table className="seamless-styled-table">
                                        <thead className="seamless-styled-thead">
                                            <tr>
                                                <th>Customer</th>
                                                <th>No. Of Products</th>
                                                <th>Ordered Products</th>
                                                <th>Status</th>
                                                <th>Total</th>
                                                <th>Ordered Date</th>
                                                <th>Invoice</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {!orders || orders.length === 0 ? (
                                                <tr><td colSpan={7} className="seamless-p-24-center-slate">No orders found.</td></tr>
                                            ) : (
                                                orders.map((order: any, i: number) => {
                                                    const statusLower = (order.status || 'unknown').toLowerCase();
                                                    let badgeClass = 'seamless-badge-default';
                                                    if (statusLower === 'completed' || statusLower === 'success') badgeClass = 'seamless-badge-success';
                                                    else if (statusLower === 'pending') badgeClass = 'seamless-badge-pending';
                                                    else if (statusLower === 'failed' || statusLower === 'cancelled') badgeClass = 'seamless-badge-failed';

                                                    const fmtDate = order.created_at ? new Date(order.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';

                                                    return (
                                                        <tr key={order.id || i} className="seamless-styled-tr">
                                                            <td>{order.customer_name || 'N/A'}</td>
                                                            <td className="seamless-text-center">{order.products_count || 1}</td>
                                                            <td>{order.ordered_product || '—'}</td>
                                                            <td><span className={`seamless-badge ${badgeClass}`}>{order.status || 'Status'}</span></td>
                                                            <td className="seamless-total-amount">${parseFloat(order.total || 0).toFixed(2)}</td>
                                                            <td className="seamless-text-slate-600">{fmtDate}</td>
                                                            <td>
                                                                {order.invoice_url ? (
                                                                    <a href={order.invoice_url} target="_blank" rel="noopener noreferrer" className="seamless-btn-invoice">Invoice</a>
                                                                ) : '—'}
                                                            </td>
                                                        </tr>
                                                    );
                                                })
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    )}
                </main>
            </div>
        </div>
    );
};
