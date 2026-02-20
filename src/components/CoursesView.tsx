import React from 'react';
import { useCourses } from '../hooks/useCourses';
import { SearchInput } from './SearchInput';
import { CustomDropdown } from './CustomDropdown';
import { Pagination } from './Pagination';
import '../styles/courses.css';

const accessOptions = [
    { value: '', label: 'All Courses' },
    { value: 'free', label: 'Free' },
    { value: 'paid', label: 'Paid' },
];



export const CoursesView: React.FC = () => {
    const {
        courses,
        pagination,
        loading,
        error,
        filters,
        availableYears,
        updateSearch,
        updateAccess,
        updateSort,
        updateYear,
        updatePage,
        resetFilters
    } = useCourses();

    const handleCourseClick = (slug: string) => {
        // External redirect or specific site route
        window.location.href = `/courses/${slug}`;
    };

    // Sort options updated: removed "All Years" from label concept, now just "Sort By" or specific
    const sortOptions = [
        { value: 'newest', label: 'Newest First' },
        { value: 'oldest', label: 'Oldest First' },
        { value: 'title_asc', label: 'A–Z' },
        { value: 'title_desc', label: 'Z–A' },
    ];

    // Year options - constructed from availableYears
    const yearOptions = [
        { value: '', label: 'All Years' },
        ...availableYears.map(year => ({ value: String(year), label: String(year) }))
    ];

    // Helper to strip HTML and truncate
    const getShortDescription = (html: string, length: number = 100) => {
        const div = document.createElement('div');
        div.innerHTML = html;
        const text = div.textContent || div.innerText || '';
        if (text.length <= length) return text;
        return text.substr(0, length).trim() + '...';
    };

    const formatDuration = (minutes: number) => {
        if (!minutes) return '0 mins';
        return `${minutes} mins`; // Or convert to hours if > 60
    };

    if (error) {
        return (
            <div id="seamless-courses-section" className="seamless-courses-container">
                <div className="seamless-error-container" style={{ textAlign: 'center', padding: '40px', color: '#ef4444' }}>
                    <p className="seamless-error-title" style={{ fontSize: '1.25rem', fontWeight: 600 }}>Error loading courses</p>
                    <p className="seamless-error-message">{error}</p>
                </div>
            </div>
        );
    }

    return (
        <div id="seamless-courses-section" className="seamless-courses-container">
            {/* Filter Bar */}
            <div className="seamless-filter-bar" style={{ marginBottom: '40px' }}>
                <div className="seamless-filter-bar-content">
                    <div className="seamless-filter-bar-inner">
                        {/* Desktop Layout */}
                        <div className="seamless-filter-bar-desktop">
                            {/* Search Header and Input - Row 1 */}
                            <div className="seamless-filter-header-row">
                                <h3 className="seamless-filter-label" style={{ fontFamily: 'Montserrat' }}>
                                    SEARCH AND FILTER
                                </h3>
                                <div className="seamless-filter-search-wrapper">
                                    <SearchInput value={filters.search} onChange={updateSearch} placeholder="Search courses by title or description..." />
                                </div>
                            </div>

                            {/* Filters Row - Row 2 */}
                            <div className="seamless-filter-controls-row">
                                {/* Access Dropdown */}
                                <div className="seamless-filter-control">
                                    <CustomDropdown
                                        value={filters.access || ''}
                                        onChange={(val) => updateAccess(val)}
                                        options={accessOptions.map(o => ({ ...o, label: o.label.toUpperCase() }))}
                                        placeholder="ALL COURSES"
                                    />
                                </div>

                                {/* Year Dropdown */}
                                <div className="seamless-filter-control">
                                    <CustomDropdown
                                        value={filters.year || ''}
                                        onChange={(val) => updateYear(val)}
                                        options={yearOptions.map(o => ({ ...o, label: o.label.toUpperCase() }))}
                                        placeholder="ALL YEARS"
                                    />
                                </div>

                                {/* Sort Dropdown */}
                                <div className="seamless-filter-control">
                                    <CustomDropdown
                                        value={filters.sort}
                                        onChange={(val) => updateSort(val)}
                                        options={sortOptions.map(o => ({ ...o, label: o.label.toUpperCase() }))}
                                        placeholder="NEWEST FIRST"
                                    />
                                </div>

                                {/* Reset Button */}
                                <div className="seamless-filter-control">
                                    <div
                                        onClick={resetFilters}
                                        className="seamless-button seamless-button-primary seamless-button-full"
                                        style={{ fontFamily: 'Montserrat' }}
                                        role="button"
                                        tabIndex={0}
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter' || e.key === ' ') {
                                                resetFilters();
                                            }
                                        }}
                                    >
                                        RESET
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Mobile Layout */}
                        <div className="seamless-filter-bar-mobile">
                            {/* Title */}
                            <h3 className="seamless-filter-label seamless-w-full" style={{ fontFamily: 'Montserrat' }}>
                                SEARCH AND FILTER
                            </h3>

                            {/* Search Bar - Full Width */}
                            <div className="seamless-filter-mobile-control">
                                <SearchInput value={filters.search} onChange={updateSearch} placeholder="Search courses by title or description..." />
                            </div>

                            {/* Filter Dropdowns - Stacked */}
                            <div className="seamless-filter-mobile-controls">
                                {/* Access Dropdown */}
                                <select
                                    value={filters.access || ''}
                                    onChange={(e) => updateAccess(e.target.value)}
                                    className="seamless-select-dropdown seamless-filter-mobile-control"
                                    style={{ fontFamily: 'Montserrat' }}
                                >
                                    {accessOptions.map(opt => (
                                        <option key={opt.value} value={opt.value}>
                                            {opt.label.toUpperCase()}
                                        </option>
                                    ))}
                                </select>

                                {/* Year Dropdown */}
                                <select
                                    value={filters.year || ''}
                                    onChange={(e) => updateYear(e.target.value)}
                                    className="seamless-select-dropdown seamless-filter-mobile-control"
                                    style={{ fontFamily: 'Montserrat' }}
                                >
                                    {yearOptions.map(opt => (
                                        <option key={opt.value} value={opt.value}>
                                            {opt.label.toUpperCase()}
                                        </option>
                                    ))}
                                </select>

                                {/* Sort Dropdown */}
                                <select
                                    value={filters.sort}
                                    onChange={(e) => updateSort(e.target.value)}
                                    className="seamless-select-dropdown seamless-filter-mobile-control"
                                    style={{ fontFamily: 'Montserrat' }}
                                >
                                    {sortOptions.map(opt => (
                                        <option key={opt.value} value={opt.value}>
                                            {opt.label.toUpperCase()}
                                        </option>
                                    ))}
                                </select>

                                {/* Reset Button */}
                                <button
                                    onClick={resetFilters}
                                    className="seamless-button seamless-button-primary seamless-button-full seamless-filter-mobile-control"
                                    style={{ fontFamily: 'Montserrat' }}
                                >
                                    RESET
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Content Area */}
            <div style={{ position: 'relative', minHeight: '300px' }}>
                {loading && (
                    <div style={{
                        position: 'absolute',
                        inset: 0,
                        display: 'flex',
                        justifyContent: 'center',
                        alignItems: 'flex-start',
                        paddingTop: '100px',
                        backgroundColor: 'rgba(255,255,255,0.8)',
                        zIndex: 10
                    }}>
                        <div className="seamless-spinner" />
                    </div>
                )}

                {/* Grid */}
                {!loading && courses.length === 0 ? (
                    <div className="seamless-empty-state" style={{ textAlign: 'center', padding: '60px 0', color: '#64748b' }}>
                        <p style={{ fontSize: '1.125rem' }}>No courses found matching your criteria.</p>
                    </div>
                ) : (
                    <div className="seamless-courses-grid">
                        {courses.map(course => (
                            <div key={course.id} className="seamless-course-card">
                                {/* Image */}
                                <div className="seamless-course-image-container">
                                    {course.image ? (
                                        <img src={course.image} alt={course.title} className="seamless-course-image" />
                                    ) : (
                                        <div style={{ width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', backgroundColor: '#e2e8f0', color: '#94a3b8' }}>
                                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                        </div>
                                    )}
                                    <div style={{ position: 'absolute', top: '16px', right: '16px' }}>
                                        <span className={`seamless-course-access-badge ${course.access_type.value === 'free' ? 'seamless-course-access-free' : 'seamless-course-access-paid'}`}>
                                            {course.access_type.label}
                                        </span>
                                    </div>
                                </div>

                                {/* Content */}
                                <div className="seamless-course-content">
                                    <h3 className="seamless-course-title">{course.title}</h3>

                                    <div className="seamless-course-description">
                                        {getShortDescription(course.description)}
                                    </div>

                                    <div className="seamless-course-meta">
                                        <div className="seamless-course-meta-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                            {/* Published Date - simple format */}
                                            {new Date(course.published_at).toLocaleDateString()}
                                        </div>
                                        <div className="seamless-course-meta-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                            {formatDuration(course.duration_minutes)}
                                        </div>
                                        {/* Optional Lessons count if available */}
                                        <div className="seamless-course-meta-item">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                                            {Array.isArray(course.lessons) ? course.lessons.length : (typeof course.lessons === 'number' ? course.lessons : 0)} lessons
                                        </div>
                                    </div>

                                    <div className="seamless-course-footer">
                                        <div className="seamless-course-price">
                                            {course.access_type.value === 'paid' && course.price ? (
                                                `$ ${Number(course.price).toFixed(2)}`
                                            ) : (
                                                'Free'
                                            )}
                                        </div>
                                        <button
                                            className="seamless-course-cta"
                                            onClick={() => handleCourseClick(course.slug)}
                                        >
                                            VIEW DETAILS
                                        </button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Pagination */}
                {pagination && pagination.total > 0 && (
                    <Pagination
                        currentPage={pagination.current_page}
                        totalPages={pagination.last_page}
                        onPageChange={updatePage}
                        showPageNumbers={true}
                    />
                )}
            </div>
        </div>
    );
};
