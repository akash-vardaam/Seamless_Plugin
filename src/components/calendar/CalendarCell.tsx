import React from 'react';
import type { Event } from '../../types/event';
import { EventBar } from './EventBar';

interface CalendarCellProps {
  day: number | null;
  isToday: boolean;
  events: Array<{
    event: Event;
    color: string;
    textColor: string;
    borderColor: string;
    startTime: string;
    spanLength: number;
    continuesFromPreviousMonth: boolean;
    continuesToNextMonth: boolean;
  }>;
}

export const CalendarCell: React.FC<CalendarCellProps> = ({
  day,
  isToday,
  events,
}) => {
  return (
    <div
      className={`seamless-calendar-cell ${
        day
          ? isToday
            ? 'seamless-calendar-cell-today'
            : ''
          : 'seamless-calendar-cell-empty'
      }`}
      style={{
        overflow: 'visible',
      }}
    >
      {day && (
        <>
          <div
            className={`seamless-calendar-cell-day ${
              isToday ? 'seamless-calendar-cell-day-today' : 'seamless-calendar-cell-day-other'
            }`}
          >
            {day}
          </div>

          {/* Events container with absolute positioning for multi-day events */}
          <div 
            className="seamless-calendar-cell-events"
            style={{
              position: 'relative',
              height: `${events.length * 2.5}rem`,
            }}
          >
            {events.map((item, idx) => (
              <EventBar
                key={`${item.event.id}-${idx}`}
                event={item.event}
                color={item.color}
                borderColor={item.borderColor}
                spanLength={item.spanLength}
                continuesFromPreviousMonth={item.continuesFromPreviousMonth}
                continuesToNextMonth={item.continuesToNextMonth}
                eventIndex={idx}
              />
            ))}
          </div>
        </>
      )}
    </div>
  );
};
