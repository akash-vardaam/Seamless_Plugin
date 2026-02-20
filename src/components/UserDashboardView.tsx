import React, { useState, useEffect } from 'react';
import '../styles/user-dashboard.css';// Type definitions for our dashboard mock data
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

export const UserDashboardView: React.FC = () => {
    // SSO / Login State
    const [isLoading, setIsLoading] = useState<boolean>(true);

    // Data State
    const [profile, setProfile] = useState<UserProfile | null>(null);
    const [memberships, setMemberships] = useState<any[]>([]);
    const [expiredMemberships, setExpiredMemberships] = useState<any[]>([]);
    const [courses, setCourses] = useState<any[]>([]);
    const [orders, setOrders] = useState<any[]>([]);

    // UI State
    const [activeView, setActiveView] = useState<'profile' | 'memberships' | 'courses' | 'orders'>('profile');
    const [isEditingProfile, setIsEditingProfile] = useState<boolean>(false);
    const [activeMembershipTab, setActiveMembershipTab] = useState<'active' | 'expired'>('active');
    const [activeCourseTab, setActiveCourseTab] = useState<'enrolled' | 'included'>('enrolled');
    const [openDropdownId, setOpenDropdownId] = useState<string | number | null>(null);

    const toggleDropdown = (id: string | number) => {
        setOpenDropdownId(openDropdownId === id ? null : id);
    };

    // Reusable Dashboard Verification
    const verifySSO = async () => {
        setIsLoading(true);
        try {
            // Fake loading state
            await new Promise(resolve => setTimeout(resolve, 500));

            // Bypass API completely and set fake user payload implicitly
            setProfile({
                first_name: 'Jane', last_name: 'Doe', email: 'jane.doe@example.com',
                phone: '(555) 123-4567', phone_type: 'mobile', address_line_1: '123 Main St',
                address_line_2: 'Apt 4B', city: 'New York', state: 'NY', zip_code: '10001', country: 'USA'
            });
            setMemberships([
                { id: '1', title: 'Gold Plan', status: 'active', purchased_date: 'Feb 13, 2026', expiration_date: 'Mar 13, 2026' },
                { id: '2', title: 'Silver Plan', status: 'active', purchased_date: 'Feb 13, 2026', expiration_date: 'Mar 13, 2026' }
            ]);
            setExpiredMemberships([]);
            setCourses([{ id: '101', title: 'Advanced React Patterns', status: 'enrolled', enrolled_date: 'Feb 10, 2026', progress: 45 }]);
            setOrders([
                { id: '1', customer: 'Actualize Studio', productsCount: 1, orderedProduct: 'Silver Plan', status: 'Completed', total: '$27.00', orderedDate: 'Feb 13, 2026' },
                { id: '2', customer: 'Actualize Studio', productsCount: 1, orderedProduct: 'Gold Plan', status: 'Completed', total: '$28.00', orderedDate: 'Feb 13, 2026' }
            ]);


        } catch (err: any) {
            console.error("Failed to set mock data:", err);
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        verifySSO();

        // Restore active tab
        const savedView = sessionStorage.getItem('seamless-user-dashboard-active-view-react');
        if (savedView && ['profile', 'memberships', 'courses', 'orders'].includes(savedView)) {
            setActiveView(savedView as any);
        }
    }, []);

    // Close dropdown when clicking outside
    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            const target = event.target as Element;
            if (!target.closest('.seamless-user-dashboard-menu-container')) {
                setOpenDropdownId(null);
            }
        };

        if (openDropdownId !== null) {
            document.addEventListener('mousedown', handleClickOutside);
        }
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [openDropdownId]);

    const switchView = (view: 'profile' | 'memberships' | 'courses' | 'orders') => {
        setActiveView(view);
        sessionStorage.setItem('seamless-user-dashboard-active-view-react', view);
        setIsEditingProfile(false); // reset edit mode
    };

    const handleProfileSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsEditingProfile(false);
        // Note: Real update call disabled safely
        // await updateUserProfile(profile); 
    };

    const handleProfileChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        if (!profile) return;
        setProfile({
            ...profile,
            [e.target.name]: e.target.value
        });
    };

    if (isLoading || !profile) {
        return (
            <div className="seamless-page-wrapper seamless-user-dashboard-loading">
                <p>Loading Component...</p>
            </div>
        );
    }

    return (
        <div id="seamless-user-dashboard-section" className="seamless-user-dashboard-section">
            <div className="seamless-user-dashboard seamless-user-dashboard-react-layout" data-widget-id="react">
                <aside className="seamless-user-dashboard-sidebar">
                    <div className="seamless-user-dashboard-profile-card">
                        <div className="seamless-user-dashboard-profile-name">{profile?.first_name} {profile?.last_name}</div>
                        <div className="seamless-user-dashboard-profile-email">Email: {profile?.email}</div>
                    </div>

                    <nav className="seamless-user-dashboard-nav">
                        <button
                            className={`seamless-user-dashboard-nav-item ${activeView === 'profile' ? 'active' : ''}`}
                            onClick={() => switchView('profile')}
                        >
                            <span>Profile</span>
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        </button>

                        <button
                            className={`seamless-user-dashboard-nav-item ${activeView === 'memberships' ? 'active' : ''}`}
                            onClick={() => switchView('memberships')}
                        >
                            <span>Memberships</span>
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        </button>

                        <button
                            className={`seamless-user-dashboard-nav-item ${activeView === 'courses' ? 'active' : ''}`}
                            onClick={() => switchView('courses')}
                        >
                            <span>Courses</span>
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        </button>

                        <button
                            className={`seamless-user-dashboard-nav-item ${activeView === 'orders' ? 'active' : ''}`}
                            onClick={() => switchView('orders')}
                        >
                            <span>Orders</span>
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        </button>

                        <button
                            onClick={() => {
                                setProfile(null);
                            }}
                            className="seamless-user-dashboard-nav-item seamless-user-dashboard-nav-logout"
                        >
                            <span>Logout</span>
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M7.5 15L12.5 10L7.5 5" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
                            </svg>
                        </button>
                    </nav>
                </aside>

                <main className="seamless-user-dashboard-main">

                    {/* Profile View */}
                    {activeView === 'profile' && (
                        <div className="seamless-user-dashboard-view active">
                            <div className="seamless-dashboard-content-container">
                                <div className="seamless-user-dashboard-profile-container">
                                    <div className="seamless-user-dashboard-profile-header">
                                        <h4 className="seamless-user-dashboard-section-title">Personal Information</h4>
                                        {!isEditingProfile && (
                                            <button
                                                className="seamless-user-dashboard-edit-btn"
                                                onClick={() => setIsEditingProfile(true)}
                                            >
                                                EDIT
                                            </button>
                                        )}
                                    </div>

                                    {!isEditingProfile ? (
                                        <div className="seamless-user-dashboard-profile-view-mode">
                                            <div className="seamless-user-dashboard-grid-2">
                                                <div>
                                                    <div className="seamless-user-dashboard-label-text">First Name</div>
                                                    <div className="seamless-user-dashboard-value-text">{profile?.first_name}</div>
                                                </div>
                                                <div>
                                                    <div className="seamless-user-dashboard-label-text">Last Name</div>
                                                    <div className="seamless-user-dashboard-value-text">{profile?.last_name}</div>
                                                </div>
                                                <div>
                                                    <div className="seamless-user-dashboard-label-text">Email Address</div>
                                                    <div className="seamless-user-dashboard-value-text">{profile?.email}</div>
                                                </div>
                                                <div>
                                                    <div className="seamless-user-dashboard-label-text">Phone Number</div>
                                                    <div className="seamless-user-dashboard-value-text">{profile?.phone} {profile?.phone_type ? `(${profile.phone_type})` : ''}</div>
                                                </div>
                                            </div>

                                            <h4 className="seamless-user-dashboard-subtitle">Address Information</h4>
                                            <div className="seamless-user-dashboard-grid-2">
                                                <div>
                                                    <div className="seamless-user-dashboard-label-text">Address Line 1</div>
                                                    <div className="seamless-user-dashboard-value-text">{profile?.address_line_1}</div>
                                                </div>
                                                <div>
                                                    <div className="seamless-user-dashboard-label-text">Address Line 2</div>
                                                    <div className="seamless-user-dashboard-value-text">{profile?.address_line_2 || '—'}</div>
                                                </div>
                                                <div>
                                                    <div className="seamless-user-dashboard-label-text">City</div>
                                                    <div className="seamless-user-dashboard-value-text">{profile?.city}</div>
                                                </div>
                                                <div>
                                                    <div className="seamless-user-dashboard-label-text">State</div>
                                                    <div className="seamless-user-dashboard-value-text">{profile?.state}</div>
                                                </div>
                                                <div>
                                                    <div className="seamless-user-dashboard-label-text">Zip Code</div>
                                                    <div className="seamless-user-dashboard-value-text">{profile?.zip_code}</div>
                                                </div>
                                                <div>
                                                    <div className="seamless-user-dashboard-label-text">Country</div>
                                                    <div className="seamless-user-dashboard-value-text">{profile?.country}</div>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <form onSubmit={handleProfileSubmit}>
                                            <div className="seamless-user-dashboard-grid-2">
                                                <div>
                                                    <label className="seamless-user-dashboard-label-text">First Name</label>
                                                    <input name="first_name" value={profile?.first_name || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" />
                                                </div>
                                                <div>
                                                    <label className="seamless-user-dashboard-label-text">Last Name</label>
                                                    <input name="last_name" value={profile?.last_name || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" />
                                                </div>
                                                <div className="seamless-user-dashboard-grid-item-span-2">
                                                    <label className="seamless-user-dashboard-label-text">Email</label>
                                                    <input type="email" name="email" value={profile?.email || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" />
                                                </div>
                                                <div>
                                                    <label className="seamless-user-dashboard-label-text">Phone Number</label>
                                                    <input name="phone" value={profile?.phone || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" />
                                                </div>
                                                <div>
                                                    <label className="seamless-user-dashboard-label-text">Phone Type</label>
                                                    <select name="phone_type" value={profile?.phone_type || 'mobile'} onChange={handleProfileChange} className="seamless-user-dashboard-input">
                                                        <option value="mobile">Mobile</option>
                                                        <option value="home">Home</option>
                                                        <option value="work">Work</option>
                                                        <option value="">Other</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <h4 className="seamless-user-dashboard-subtitle">Address Information</h4>
                                            <div className="seamless-user-dashboard-grid-2 margin-bottom-30">
                                                <div className="seamless-user-dashboard-grid-item-span-2">
                                                    <label className="seamless-user-dashboard-label-text">Address Line 1</label>
                                                    <input name="address_line_1" value={profile?.address_line_1 || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" />
                                                </div>
                                                <div className="seamless-user-dashboard-grid-item-span-2">
                                                    <label className="seamless-user-dashboard-label-text">Address Line 2</label>
                                                    <input name="address_line_2" value={profile?.address_line_2 || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" />
                                                </div>
                                                <div>
                                                    <label className="seamless-user-dashboard-label-text">City</label>
                                                    <input name="city" value={profile?.city || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" />
                                                </div>
                                                <div>
                                                    <label className="seamless-user-dashboard-label-text">State</label>
                                                    <input name="state" value={profile?.state || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" />
                                                </div>
                                                <div>
                                                    <label className="seamless-user-dashboard-label-text">Zip Code</label>
                                                    <input name="zip_code" value={profile?.zip_code || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" />
                                                </div>
                                                <div>
                                                    <label className="seamless-user-dashboard-label-text">Country</label>
                                                    <input name="country" value={profile?.country || ''} onChange={handleProfileChange} className="seamless-user-dashboard-input" />
                                                </div>
                                            </div>

                                            <div className="seamless-user-dashboard-form-actions">
                                                <button
                                                    type="submit"
                                                    className="seamless-user-dashboard-btn-primary"
                                                >
                                                    Save Changes
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setIsEditingProfile(false)}
                                                    className="seamless-user-dashboard-btn-secondary"
                                                >
                                                    Cancel
                                                </button>
                                            </div>
                                        </form>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Memberships View */}
                    {activeView === 'memberships' && (
                        <div className="seamless-user-dashboard-view active">
                            <div className="seamless-dashboard-content-container">
                                <div className="seamless-user-dashboard-summary-grid seamless-user-dashboard-summary-grid-memberships">
                                    <div className="seamless-user-dashboard-summary-card">
                                        <div className="seamless-user-dashboard-summary-value">{memberships.length}</div>
                                        <div className="seamless-user-dashboard-summary-label">Total Active Memberships</div>
                                    </div>
                                    {memberships.length > 0 ? (
                                        <div className="seamless-user-dashboard-current-membership-card">
                                            <div className="seamless-user-dashboard-current-membership-header">
                                                <h3>{memberships[memberships.length - 1].title || memberships[memberships.length - 1].name}</h3>
                                                <button className="seamless-user-dashboard-badge">{(memberships[memberships.length - 1].status || 'active').charAt(0).toUpperCase() + (memberships[memberships.length - 1].status || 'active').slice(1)}</button>
                                            </div>
                                            <div className="seamless-user-dashboard-current-membership-body">
                                                <div className="seamless-user-dashboard-current-membership-expiry">
                                                    Expires on: <strong>{memberships[memberships.length - 1].expiration_date || 'N/A'}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="seamless-user-dashboard-current-membership-card seamless-user-dashboard-empty-membership">
                                            <div className="seamless-user-dashboard-summary-message">
                                                <p>No active memberships found.</p>
                                            </div>
                                        </div>
                                    )}
                                </div>
                                <div className="seamless-user-dashboard-tabs-wrapper">
                                    <div className="seamless-user-dashboard-tabs-header">
                                        <button
                                            className={`seamless-user-dashboard-tab ${activeMembershipTab === 'active' ? 'active' : ''}`}
                                            onClick={() => setActiveMembershipTab('active')}
                                        >
                                            Active Memberships <span className="seamless-user-dashboard-tab-count">{memberships.length}</span>
                                        </button>
                                        <button
                                            className={`seamless-user-dashboard-tab ${activeMembershipTab === 'expired' ? 'active' : ''}`}
                                            onClick={() => setActiveMembershipTab('expired')}
                                        >
                                            Expired Memberships
                                        </button>
                                    </div>
                                    <div className="seamless-user-dashboard-tab-content active seamless-user-dashboard-tab-content-padded">
                                        {activeMembershipTab === 'active' ? (
                                            memberships.length === 0 ? (
                                                <p className="seamless-user-dashboard-empty-text">No active memberships found.</p>
                                            ) : (
                                                memberships.map((mem: any, i) => (
                                                    <div key={mem.id || i} className="seamless-user-dashboard-membership-card">
                                                        <div className="seamless-user-dashboard-flex-between">
                                                            <div>
                                                                <div className="seamless-user-dashboard-flex-center">
                                                                    <h4 className="seamless-user-dashboard-card-title">{mem.title || mem.name || 'Membership Plan'}</h4>
                                                                </div>
                                                                <div className="seamless-user-dashboard-expiry-info">
                                                                    Purchased: <strong>{mem.purchased_date || 'N/A'}</strong> &nbsp;&nbsp;&nbsp; Expires: <strong>{mem.expiration_date || mem.end_date || 'N/A'}</strong>
                                                                </div>
                                                            </div>
                                                            <div className="seamless-user-dashboard-flex-center">
                                                                <span className="seamless-user-dashboard-badge seamless-user-dashboard-badge-active">{(mem.status || 'active').toUpperCase()}</span>
                                                                <div className={`seamless-user-dashboard-menu-container ${openDropdownId === (mem.id || i) ? 'active' : ''}`}>
                                                                    <button className="seamless-user-dashboard-menu-button" onClick={() => toggleDropdown(mem.id || i)}>
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                            <path d="M9.5 13a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0zm0-5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z" />
                                                                        </svg>
                                                                    </button>
                                                                    <div className="seamless-user-dashboard-menu-dropdown">
                                                                        <button className="seamless-user-dashboard-menu-item seamless-user-dashboard-badge-upgrade">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
                                                                            Upgrade
                                                                        </button>
                                                                        <button className="seamless-user-dashboard-menu-item seamless-user-dashboard-badge-cancel">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                                                            Cancel
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))
                                            )
                                        ) : (
                                            expiredMemberships.length === 0 ? (
                                                <p className="seamless-user-dashboard-empty-text text-center seamless-user-dashboard-expired-message">No expired memberships found.</p>
                                            ) : (
                                                expiredMemberships.map((mem: any, i) => (
                                                    <div key={mem.id || i} className="seamless-user-dashboard-membership-card">
                                                        <div className="seamless-user-dashboard-flex-between">
                                                            <div>
                                                                <div className="seamless-user-dashboard-flex-center">
                                                                    <h4 className="seamless-user-dashboard-card-title">{mem.title || mem.name || 'Membership Plan'}</h4>
                                                                </div>
                                                                <div className="seamless-user-dashboard-expiry-info">
                                                                    Expired: <strong>{mem.expiration_date || mem.end_date || 'N/A'}</strong>
                                                                </div>
                                                            </div>
                                                            <div className="seamless-user-dashboard-flex-center">
                                                                <span className="seamless-user-dashboard-badge seamless-user-dashboard-badge-expired">{(mem.status || 'expired').toUpperCase()}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))
                                            )
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Courses View */}
                    {activeView === 'courses' && (
                        <div className="seamless-user-dashboard-view active">
                            <div className="seamless-dashboard-content-container">
                                <div className="seamless-user-dashboard-summary-grid seamless-user-dashboard-summary-grid-memberships">
                                    <div className="seamless-user-dashboard-summary-card">
                                        <div className="seamless-user-dashboard-summary-value">{courses.length}</div>
                                        <div className="seamless-user-dashboard-summary-label">Total Courses</div>
                                    </div>
                                    <div className="seamless-user-dashboard-summary-card">
                                        <div className="seamless-user-dashboard-summary-value">{courses.filter(c => c.progress === 100).length}</div>
                                        <div className="seamless-user-dashboard-summary-label">Completed</div>
                                    </div>
                                    {courses.some(c => c.progress > 0 && c.progress < 100) ? (
                                        <div className="seamless-user-dashboard-current-membership-card">
                                            {/* Show first in progress course */}
                                            {courses.filter(c => c.progress > 0 && c.progress < 100).slice(0, 1).map((course, i) => (
                                                <div key={course.id || i} className="seamless-course-in-progress-wrapper">
                                                    <h4 className="seamless-user-dashboard-course-title">{course.title || course.name}</h4>
                                                    <div className="seamless-user-dashboard-progress-text">
                                                        <span className="seamless-user-dashboard-progress-text-value">{course.progress}% Complete</span>
                                                        <span className="seamless-user-dashboard-status-text">IN PROGRESS</span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="seamless-user-dashboard-summary-card seamless-user-dashboard-empty-membership" style={{ gridColumn: 'span 2' }}>
                                            <div className="seamless-user-dashboard-summary-message text-center">
                                                <svg className="seamless-user-dashboard-empty-icon" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                                                <p>No courses in progress</p>
                                            </div>
                                        </div>
                                    )}
                                </div>
                                <div className="seamless-user-dashboard-tabs-wrapper">
                                    <div className="seamless-user-dashboard-tabs-header">
                                        <button
                                            className={`seamless-user-dashboard-tab ${activeCourseTab === 'enrolled' ? 'active' : ''}`}
                                            onClick={() => setActiveCourseTab('enrolled')}
                                        >
                                            Enrolled Courses
                                        </button>
                                        <button
                                            className={`seamless-user-dashboard-tab ${activeCourseTab === 'included' ? 'active' : ''}`}
                                            onClick={() => setActiveCourseTab('included')}
                                        >
                                            Included in Membership
                                        </button>
                                    </div>
                                    <div className="seamless-user-dashboard-tab-content active seamless-user-dashboard-tab-content-no-padding">
                                        {activeCourseTab === 'enrolled' ? (
                                            courses.length === 0 ? (
                                                <div className="seamless-user-dashboard-empty-state">
                                                    <p className="seamless-user-dashboard-empty-text">You have not enrolled in any courses yet.</p>
                                                </div>
                                            ) : (
                                                <div className="seamless-user-dashboard-tab-content-section">
                                                    {courses.map((course: any, i) => (
                                                        <div key={course.id || i} className="seamless-user-dashboard-course-card">
                                                            <h4 className="seamless-user-dashboard-course-title">{course.title || course.name}</h4>
                                                            <div className="seamless-user-dashboard-course-date">Enrolled: {course.enrolled_date || course.created_at || 'N/A'}</div>
                                                            {course.progress !== undefined && (
                                                                <>
                                                                    <div className="seamless-user-dashboard-progress-bar-bg">
                                                                        <div className={`seamless-user-dashboard-progress-fill ${course.progress === 100 ? 'completed' : ''}`} style={{ width: `${course.progress}%` }} />
                                                                    </div>
                                                                    <div className="seamless-user-dashboard-progress-text">
                                                                        <span className="seamless-user-dashboard-progress-text-value">{course.progress}% Complete</span>
                                                                        <span className={`seamless-user-dashboard-status-text ${course.progress === 100 ? 'completed' : ''}`}>{(course.progress === 100 ? 'completed' : 'in progress').toUpperCase()}</span>
                                                                    </div>
                                                                </>
                                                            )}
                                                        </div>
                                                    ))}
                                                </div>
                                            )
                                        ) : (
                                            <div className="seamless-user-dashboard-empty-state">
                                                <p className="seamless-user-dashboard-empty-text">You do not have any courses included in your membership.</p>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Orders View */}
                    {activeView === 'orders' && (
                        <div className="seamless-user-dashboard-view active">
                            <div className="seamless-dashboard-content-container">
                                <h3 className="seamless-user-dashboard-view-title">Order History</h3>
                                <div className="seamless-user-dashboard-table-wrapper">
                                    <table className="seamless-user-dashboard-table">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>No. Of<br />Products</th>
                                                <th>Ordered Products</th>
                                                <th>Status</th>
                                                <th>Total</th>
                                                <th>Ordered Date</th>
                                                <th className="text-center">Invoice</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {orders.length === 0 ? (
                                                <tr>
                                                    <td colSpan={7} className="seamless-user-dashboard-table-empty">No orders found.</td>
                                                </tr>
                                            ) : (
                                                orders.map((order: any, i) => (
                                                    <tr key={order.id || i}>
                                                        <td className="customer-cell">
                                                            {order.customer ? order.customer.split(' ').map((n: string) => <React.Fragment key={n}>{n}<br /></React.Fragment>) : 'Actulize Devs'}
                                                        </td>
                                                        <td className="text-center">{order.productsCount || 1}</td>
                                                        <td>{order.orderedProduct || 'Plan'}</td>
                                                        <td>
                                                            <span className="seamless-user-dashboard-badge-completed">{(order.status || 'completed')}</span>
                                                        </td>
                                                        <td className="order-total-cell">
                                                            <div className="order-total-flex">
                                                                {order.total || '$0.00'}
                                                            </div>
                                                        </td>
                                                        <td>{order.orderedDate || order.date || 'N/A'}</td>
                                                        <td className="text-center">
                                                            <button className="seamless-user-dashboard-invoice-btn">Invoice</button>
                                                        </td>
                                                    </tr>
                                                ))
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
