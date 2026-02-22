import React from 'react';

interface CalendarHeaderProps {
  viewMode: 'MONTH' | 'WEEK';
  onViewModeChange: (mode: 'MONTH' | 'WEEK') => void;
  title: string;
  subtitle: string;
  onPrev: () => void;
  onNext: () => void;
  onToday: () => void;
}

export const CalendarHeader: React.FC<CalendarHeaderProps> = ({
  viewMode,
  onViewModeChange,
  title,
  subtitle,
  onPrev,
  onNext,
  onToday,
}) => {
  const today = new Date();
  const todayMonth = today.toLocaleDateString('en-US', { month: 'short' }).toUpperCase();
  const todayDate = today.getDate();

  return (
    <div className="seamless-calendar-header-modern">
      <div className="seamless-calendar-header-left">
        <div className="seamless-calendar-today-box" onClick={onToday}>
          <span className="seamless-calendar-today-month">{todayMonth}</span>
          <span className="seamless-calendar-today-date">{todayDate}</span>
        </div>
        <div className="seamless-calendar-title-stack">
          <h2 className="seamless-calendar-main-title">{title}</h2>
          <span className="seamless-calendar-subtitle">{subtitle}</span>
        </div>
      </div>
      <div className="seamless-calendar-header-right">
        <div className="seamless-calendar-nav-buttons">
          <button className="seamless-calendar-nav-btn" onClick={onPrev}>&lt;</button>
          <button className="seamless-calendar-nav-btn seamless-calendar-today-btn" onClick={onToday}>
            {viewMode === 'MONTH' ? 'THIS MONTH' : 'THIS WEEK'}
          </button>
          <button className="seamless-calendar-nav-btn" onClick={onNext}>&gt;</button>
        </div>
        <div className="seamless-calendar-view-toggle">
          <button 
            className={`seamless-calendar-view-btn ${viewMode === 'MONTH' ? 'active' : ''}`}
            onClick={() => onViewModeChange('MONTH')}
          >
            {viewMode === 'MONTH' ? '( MONTH )' : 'MONTH'}
          </button>
          <button 
            className={`seamless-calendar-view-btn ${viewMode === 'WEEK' ? 'active' : ''}`}
            onClick={() => onViewModeChange('WEEK')}
          >
            {viewMode === 'WEEK' ? '( WEEK )' : 'WEEK'}
          </button>
        </div>
      </div>
    </div>
  );
};
