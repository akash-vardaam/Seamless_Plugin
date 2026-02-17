import React from 'react';
import type { Event } from '../../types/event';
import { navigateToEvent, createEventSlug } from '../../utils/urlHelper';

interface EventBarProps {
  event: Event;
  color: string;
  borderColor: string;
  spanLength?: number;
  continuesFromPreviousMonth?: boolean;
  continuesToNextMonth?: boolean;
  eventIndex?: number;
}

const formatDateRange = (startDate: string, endDate: string): string => {
  try {
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    // If same date, just return formatted date once
    if (start.toDateString() === end.toDateString()) {
      return start.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    }
    
    // Different dates - show range
    const startDay = start.toLocaleDateString('en-US', { weekday: 'long' });
    const endDay = end.toLocaleDateString('en-US', { weekday: 'long' });
    const startDateStr = start.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });
    const endDateStr = end.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    
    return `${startDay} - ${endDay}, ${startDateStr} - ${endDateStr}`;
  } catch {
    return startDate;
  }
};

const formatTimeRange = (startDate: string, endDate: string): string => {
  try {
    const start = new Date(startDate);
    const end = new Date(endDate);
    const startTime = start.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    const endTime = end.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    const timezone = 'CDT';
    return `${startTime} – ${endTime} ${timezone}`;
  } catch {
    return '';
  }
};

const handleEventClick = (event: Event) => {
  navigateToEvent(event.slug || createEventSlug(event.title, event.id));
};

export const EventBar: React.FC<EventBarProps> = ({
  event,
  color,
  borderColor,
  spanLength = 1,
  continuesFromPreviousMonth = false,
  continuesToNextMonth = false,
  eventIndex = 0,
}) => {
  const handleClick = () => {
    handleEventClick(event);
  };

  const dateRange = formatDateRange(event.start_date, event.end_date || event.start_date);
  const timeRange = formatTimeRange(event.start_date, event.end_date || event.start_date);
  const widthPercentage = spanLength * 100;
  const gapSize = (spanLength - 1) * 8;
  const dynamicWidth = `calc(${widthPercentage}% + ${gapSize}px)`;

  // Position from top based on event index
  const topPosition = `${eventIndex * 2.5}rem`;

  return (
    <div
      className="seamless-calendar-event-bar"
      style={{
        position: 'absolute',
        left: '0',
        top: topPosition,
        width: dynamicWidth,
        backgroundColor: color,
        borderLeftColor: borderColor,
        borderLeftStyle: continuesFromPreviousMonth ? 'dotted' : 'solid',
        zIndex: 10,
        display: 'flex',
        alignItems: 'center',
      }}
      title={event.title}
      onClick={handleClick}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          handleClick();
        }
      }}
      role="button"
      tabIndex={0}
    >
      {/* Continuation indicator from previous month */}
      {continuesFromPreviousMonth && (
        <span className="seamless-calendar-event-bar-continues-from">←</span>
      )}

      {/* Main truncated text */}
      <div className="seamless-calendar-event-bar-text">
        {event.title}
      </div>

      {/* Continuation indicator to next month */}
      {continuesToNextMonth && (
        <span className="seamless-calendar-event-bar-continues-to">→</span>
      )}

      {/* Tooltip on hover - responsive positioning */}
      <div className="seamless-calendar-event-tooltip">
        <div className="seamless-calendar-event-tooltip-title">{event.title}</div>
        <div className="seamless-calendar-event-tooltip-date">{dateRange}</div>
        <div className="seamless-calendar-event-tooltip-time">{timeRange}</div>
      </div>
    </div>
  );
};
