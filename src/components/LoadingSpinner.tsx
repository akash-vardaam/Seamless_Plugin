import React from 'react';

interface LoadingSpinnerProps {
    fullHeight?: boolean;
}

export const LoadingSpinner: React.FC<LoadingSpinnerProps> = ({ fullHeight = true }) => {
    return (
        <div className={fullHeight ? "seamless-loading-container" : "seamless-loading-inline"} style={!fullHeight ? { display: 'flex', justifyContent: 'center', alignItems: 'center', width: '100%', padding: '40px 0' } : undefined}>
            <div className="seamless-loading-content">
                <div className="seamless-spinner" />
            </div>
        </div>
    );
};
