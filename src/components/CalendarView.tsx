import React, { useState, useMemo } from 'react';
import type { Event } from '../types/event';
import { CalendarHeader } from './calendar/CalendarHeader';
import { CalendarDayHeaders } from './calendar/CalendarDayHeaders';
import { CalendarCell } from './calendar/CalendarCell';
import {
  getCategoryColor,
  getStartTime,
  extractDateOnly,
  getDateParts,
} from './calendar/utils';

interface CalendarViewProps {
  events: Event[];
}

interface EventBar {
  event: Event;
  row: number;
  startCol: number;
  endCol: number;
  color: string;
  textColor: string;
  borderColor: string;
  daysSpanned: number;
  isFirstDay: boolean;
  spanLength: number;
  continuesFromPreviousMonth: boolean;
  continuesToNextMonth: boolean;
}

export const CalendarView: React.FC<CalendarViewProps> = ({ events }) => {
  const [currentDate, setCurrentDate] = useState(new Date());
  
  // Debug logging
  React.useEffect(() => {
    console.log('CalendarView received events:', events);
    if (events.length > 0) {
      console.log('First event:', events[0]);
      console.log('First event start_date:', events[0].start_date);
      console.log('First event end_date:', events[0].end_date);
    }
  }, [events]);

  const getDaysInMonth = (date: Date) => {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
  };

  const getFirstDayOfMonth = (date: Date) => {
    return new Date(date.getFullYear(), date.getMonth(), 1).getDay();
  };

  // Create a map of events by day for easier lookup
  const eventsByDay = useMemo(() => {
    const dayMap = new Map<number, EventBar[]>();
    
    console.log('eventsByDay memo - processing events:', events.length);

    // Get the first and last day of the current month
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const monthStart = new Date(year, month, 1);
    const monthEnd = new Date(year, month + 1, 0);
    const daysInMonth = monthEnd.getDate();

    // Initialize dayMap with empty arrays for each day
    for (let day = 1; day <= daysInMonth; day++) {
      dayMap.set(day, []);
    }

    // Filter and process events
    events.forEach((event, idx) => {
      try {
        const startDateOnly = extractDateOnly(event.start_date);
        const endDateOnly = extractDateOnly(event.end_date || event.start_date);
        
        console.log(`Event ${idx}: ${event.title}, start: ${startDateOnly}, end: ${endDateOnly}`);

        const startParts = getDateParts(startDateOnly);
        const endParts = getDateParts(endDateOnly);
        
        console.log(`  Parsed - Start: ${startParts.year}-${startParts.month}-${startParts.day}, End: ${endParts.year}-${endParts.month}-${endParts.day}`);
        console.log(`  Current month: ${year}-${month + 1}`);

        // Check if event overlaps with current month
        const eventStartDate = new Date(
          startParts.year,
          startParts.month - 1,
          startParts.day
        );
        const eventEndDate = new Date(
          endParts.year,
          endParts.month - 1,
          endParts.day
        );

        if (eventEndDate >= monthStart && eventStartDate <= monthEnd) {
          console.log(`  ✓ Event overlaps with current month`);
          
          // Determine if event continues from previous month
          const continuesFromPreviousMonth = eventStartDate < monthStart;
          // Determine if event continues to next month
          const continuesToNextMonth = eventEndDate > monthEnd;
          
          // Add event to all days it spans in this month
          let currentDay = Math.max(startParts.day, 1);
          let endDay = Math.min(endParts.day, daysInMonth);

          // Adjust start day if event starts before this month
          if (
            startParts.year < year ||
            (startParts.year === year && startParts.month < month + 1)
          ) {
            currentDay = 1;
          }

          // Adjust end day if event ends after this month
          if (
            endParts.year > year ||
            (endParts.year === year && endParts.month > month + 1)
          ) {
            endDay = daysInMonth;
          }

          const color = getCategoryColor(event);
          const spanLength = endDay - currentDay + 1;
          const eventBar: EventBar = {
            event,
            row: 0,
            startCol: currentDay,
            endCol: endDay + 1,
            color: color.bg,
            textColor: color.text,
            borderColor: color.border,
            daysSpanned: spanLength,
            isFirstDay: !continuesFromPreviousMonth,
            spanLength: spanLength,
            continuesFromPreviousMonth,
            continuesToNextMonth,
          };

          // Add to first day only for multi-day events
          const firstDayEvents = dayMap.get(currentDay) || [];
          firstDayEvents.push(eventBar);
          dayMap.set(currentDay, firstDayEvents);
          
          console.log(`  Added to day ${currentDay} with span ${spanLength}`);
        } else {
          console.log(`  ✗ Event does NOT overlap with current month`);
        }
      } catch (err) {
        console.error('Error processing event:', event, err);
      }
    });

    console.log('eventsByDay final map:', dayMap);
    return dayMap;
  }, [currentDate, events]);

  const daysInMonth = getDaysInMonth(currentDate);
  const firstDay = getFirstDayOfMonth(currentDate);
  const days: (number | null)[] = [];

  for (let i = 0; i < firstDay; i++) {
    days.push(null);
  }

  for (let day = 1; day <= daysInMonth; day++) {
    days.push(day);
  }

  const monthName = currentDate.toLocaleString('default', {
    month: 'long',
    year: 'numeric',
  });

  const handlePrevMonth = () => {
    setCurrentDate(
      new Date(currentDate.getFullYear(), currentDate.getMonth() - 1)
    );
  };

  const handleNextMonth = () => {
    setCurrentDate(
      new Date(currentDate.getFullYear(), currentDate.getMonth() + 1)
    );
  };

  return (
    <div className="seamless-calendar">
      {/* Header */}
      <CalendarHeader
        monthName={monthName}
        onPrevMonth={handlePrevMonth}
        onNextMonth={handleNextMonth}
      />

      {/* Day Headers */}
      <CalendarDayHeaders abbreviated={true} />

      {/* Calendar Grid - Responsive */}
      <div className="seamless-calendar-grid">
        {days.map((day, idx) => {
          const isToday =
            day &&
            day === new Date().getDate() &&
            currentDate.getMonth() === new Date().getMonth() &&
            currentDate.getFullYear() === new Date().getFullYear();

          // Get events for this day from the map
          const cellEvents = (day ? eventsByDay.get(day) : []) || [];
          
          const mappedEvents = cellEvents.map((bar) => ({
            event: bar.event,
            color: bar.color,
            textColor: bar.textColor,
            borderColor: bar.borderColor,
            startTime: getStartTime(bar.event.start_date),
            spanLength: bar.spanLength,
            continuesFromPreviousMonth: bar.continuesFromPreviousMonth,
            continuesToNextMonth: bar.continuesToNextMonth,
          }));

          return (
            <CalendarCell
              key={idx}
              day={day}
              isToday={isToday || false}
              events={mappedEvents}
            />
          );
        })}
      </div>
    </div>
  );
};
