import React, { useState } from 'react';
import type { Event } from '../types/event';
import { CalendarHeader } from './calendar/CalendarHeader';
import { MonthView } from './calendar/MonthView';
import { WeekView } from './calendar/WeekView';
import '../styles/calendar-new.css';

interface CalendarViewProps {
  events: Event[];
  currentDate?: Date;
  onDateChange?: (date: Date) => void;
}

export const CalendarView: React.FC<CalendarViewProps> = ({ events, currentDate: propDate, onDateChange }) => {
  const [internalDate, setInternalDate] = useState(new Date());
  const activeDate = propDate || internalDate;
  
  const [viewMode, setViewMode] = useState<'MONTH' | 'WEEK'>('MONTH');

  const updateDate = (d: Date) => {
    if (onDateChange) onDateChange(d);
    else setInternalDate(d);
  };

  const handlePrev = () => {
    const d = new Date(activeDate);
    if (viewMode === 'MONTH') d.setMonth(d.getMonth() - 1);
    else d.setDate(d.getDate() - 7);
    updateDate(d);
  };

  const handleNext = () => {
    const d = new Date(activeDate);
    if (viewMode === 'MONTH') d.setMonth(d.getMonth() + 1);
    else d.setDate(d.getDate() + 7);
    updateDate(d);
  };

  const handleToday = () => {
    updateDate(new Date());
  };

  let title = '';
  let subtitle = '';

  if (viewMode === 'MONTH') {
    title = activeDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
    const firstDay = new Date(activeDate.getFullYear(), activeDate.getMonth(), 1);
    const lastDay = new Date(activeDate.getFullYear(), activeDate.getMonth() + 1, 0);
    subtitle = `${firstDay.toLocaleDateString('en-US', {month:'short', day:'numeric'})} — ${lastDay.toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'})}`;
  } else if (viewMode === 'WEEK') {
    title = 'Week View';
    const day = activeDate.getDay();
    const diff = activeDate.getDate() - day; 
    const sunday = new Date(activeDate);
    sunday.setDate(diff);
    const saturday = new Date(sunday);
    saturday.setDate(sunday.getDate() + 6);
    subtitle = `${sunday.toLocaleDateString('en-US', {month:'short', day:'numeric'})} — ${saturday.toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'})}`;
  }

  return (
    <div className="seamless-calendar-container">
      <CalendarHeader
        viewMode={viewMode}
        onViewModeChange={setViewMode}
        title={title}
        subtitle={subtitle}
        onPrev={handlePrev}
        onNext={handleNext}
        onToday={handleToday}
      />

      <div className="seamless-calendar-body">
        {viewMode === 'MONTH' && <MonthView currentDate={activeDate} events={events} />}
        {viewMode === 'WEEK' && <WeekView currentDate={activeDate} events={events} />}
      </div>
    </div>
  );
};
