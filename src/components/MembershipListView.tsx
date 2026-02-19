
import React, { useMemo } from 'react';
import { useMembershipPlans } from '../hooks/useMembershipPlans';
import '../styles/membership.css';

// Helper to get site URL
const getSiteUrl = () => {
    if (typeof window !== 'undefined' && (window as any).seamlessReactConfig?.siteUrl) {
        return (window as any).seamlessReactConfig.siteUrl;
    }
    return ''; // Fallback or current origin
};

export const MembershipListView: React.FC = () => {
    const { plans, loading, error } = useMembershipPlans();

    // 1. Comparison Data Logic
    const comparisonKeys = useMemo(() => {
        const keys = new Set<string>();
        plans.forEach(plan => {
            if (plan.content_rules) {
                Object.keys(plan.content_rules).forEach(k => keys.add(k));
            }
        });
        return Array.from(keys);
    }, [plans]);

    if (loading) {
        return (
            <div className="seamless-loading-overlay" style={{ minHeight: '400px', display: 'flex', justifyContent: 'center', alignItems: 'center' }}>
                <div className="seamless-spinner" />
            </div>
        );
    }

    if (error) {
        return (
            <div className="seamless-error-container">
                <p className="seamless-error-title">Error loading plans</p>
                <p className="seamless-error-message">{error}</p>
            </div>
        );
    }

    const siteUrl = getSiteUrl();

    return (
        <div id="seamless-membership-section" className="seamless-membership-container">
            <header className="seamless-membership-header">
                <h1 className="seamless-membership-title">Start your Membership</h1>
                <p className="seamless-membership-subtitle">Choose a plan that fits your needs.</p>
            </header>

            {/* Plans Grid */}
            <div className="seamless-plans-grid">
                {plans.map(plan => (
                    <div key={plan.id} className="seamless-plan-card">
                        <div className="seamless-plan-header-row">
                            <div className="seamless-plan-header-left">
                                <h3 className="seamless-plan-label">{plan.label}</h3>
                                <div className="seamless-plan-badge">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" style={{ marginRight: '6px' }}><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                    Subscription plan
                                </div>
                            </div>
                            <div className="seamless-plan-header-right">
                                <div className="seamless-plan-price-badge">
                                    <span className="seamless-currency">$</span>
                                    <span className="seamless-amount">{plan.price}</span>
                                </div>
                                <div className="seamless-plan-renewal">
                                    {plan.billing_cycle_display}
                                </div>
                            </div>
                        </div>

                        <div className="seamless-plan-divider" />

                        <div className="seamless-plan-features-list">
                            {/* 1. Signup Fee */}
                            {plan.signup_fee > 0 && (
                                <div className="seamless-feature-item">
                                    <span className="seamless-check">✓</span>
                                    <span>Signup fee ${plan.signup_fee.toFixed(2)}</span>
                                </div>
                            )}

                            {/* 2. Content Rules (Features) */}
                            {plan.content_rules && Object.entries(plan.content_rules).map(([key, value]) => (
                                <div key={key} className="seamless-feature-item">
                                    <span className="seamless-check">✓</span>
                                    <span>{key}: {String(value)}</span>
                                </div>
                            ))}

                            {/* 3. Description (Fallback if no rules) - Careful not to double render if rules exist */}
                            {(!plan.content_rules || Object.keys(plan.content_rules).length === 0) && (
                                <div
                                    className="seamless-plan-description-html"
                                    dangerouslySetInnerHTML={{ __html: plan.description }}
                                />
                            )}
                        </div>

                        <div className="seamless-plan-footer">
                            <a
                                href={`${siteUrl.replace(/\/$/, '')}/${plan.id}`}
                                className="seamless-plan-cta"
                            >
                                GET STARTED
                            </a>
                        </div>
                    </div>
                ))}
            </div>

            {/* Comparison Table */}
            {comparisonKeys.length > 0 && (
                <div className="seamless-comparison-section">
                    <h2 className="seamless-comparison-title">Compare Plans</h2>
                    <div style={{ overflowX: 'auto' }}>
                        <table className="seamless-comparison-table">
                            <thead>
                                <tr>
                                    <th className="seamless-comparison-header-cell">Offering</th>
                                    {plans.map(plan => (
                                        <th key={plan.id} className="seamless-comparison-header-cell">
                                            <span className="seamless-comparison-plan-name">{plan.label}</span>
                                            <span className="seamless-comparison-plan-price">${plan.price} / {plan.billing_cycle_display}</span>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {comparisonKeys.map(key => (
                                    <tr key={key} className="seamless-comparison-row">
                                        <td>{key}</td>
                                        {plans.map(plan => (
                                            <td key={`${plan.id}-${key}`} className="seamless-comparison-value">
                                                {plan.content_rules?.[key] !== undefined
                                                    ? String(plan.content_rules[key])
                                                    : '—'}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </div>
    );
};
