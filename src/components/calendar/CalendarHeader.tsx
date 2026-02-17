import React from 'react';

interface CalendarHeaderProps {
  monthName: string;
  onPrevMonth: () => void;
  onNextMonth: () => void;
}

export const CalendarHeader: React.FC<CalendarHeaderProps> = ({
  monthName,
  onPrevMonth,
  onNextMonth,
}) => {
  return (
    <div className="seamless-calendar-header">
      <span
        onClick={onPrevMonth}
        className="seamless-calendar-nav-button"
        aria-label="Previous month"
      >
        ← Prev
      </span>
      <h2 className="seamless-calendar-title">
        {monthName}
      </h2>
      <span
        onClick={onNextMonth}
        className="seamless-calendar-nav-button"
        aria-label="Next month"
      >
        Next →
      </span>
    </div>
  );
};
