import React, { useMemo } from 'react';
import type { Event } from '../../types/event';
import { getCategoryColor, extractDateOnly } from './utils';
import { navigateToEvent, createEventSlug } from '../../utils/urlHelper';

interface DayViewProps {
  currentDate: Date;
  events: Event[];
}

const HOUR_HEIGHT = 60;

export const DayView: React.FC<DayViewProps> = ({ currentDate, events }) => {
  const isToday = currentDate.toDateString() === new Date().toDateString();
  const hours = Array.from({length: 24}, (_, i) => i);

  const { dayEvents, allDayEvents } = useMemo(() => {
    let dayEvts: any[] = [];
    let allDayEvts: any[] = [];

    events.forEach(e => {
      try {
        const startDateOnly = extractDateOnly(e.start_date);
        const endDateOnly = extractDateOnly(e.end_date || e.start_date);
        const startRaw = new Date(e.start_date);
        let endRaw = e.end_date ? new Date(e.end_date) : new Date(startRaw.getTime() + 60*60*1000);

        if (startDateOnly !== endDateOnly || e?.all_day) {
           allDayEvts.push(e);
           return;
        }

        if (startRaw.toDateString() === currentDate.toDateString()) {
           const startHour = startRaw.getHours() + startRaw.getMinutes() / 60;
           let endHour = endRaw.getHours() + endRaw.getMinutes() / 60;
           if (endHour <= startHour) endHour = startHour + 1;

           dayEvts.push({
             event: e,
             top: startHour * HOUR_HEIGHT,
             height: (endHour - startHour) * HOUR_HEIGHT,
           });
        }
      } catch(err) {}
    });
    return { dayEvents: dayEvts, allDayEvents: allDayEvts };
  }, [events, currentDate]);

  return (
    <div className="seamless-week-view day-only-view">
      <div className="seamless-week-header-row">
        <div className="seamless-week-time-gutter">
           <div className="seamless-week-allday-label">All Day</div>
        </div>
        <div className="seamless-week-days single-day-mode">
            <div className={`seamless-week-day-header ${isToday ? 'today' : ''}`}>
              <span className="seamless-week-date-num">{currentDate.getDate()}</span>
              <span className="seamless-week-day-name">{currentDate.toLocaleDateString('en-US', { weekday: 'long' }).toUpperCase()}</span>
            </div>
        </div>
      </div>

      <div className="seamless-week-allday-row">
         <div className="seamless-week-time-gutter"></div>
         <div className="seamless-week-allday-content">
            {allDayEvents.map((e, idx) => {
              const color = getCategoryColor(e);
               return (
                 <div key={idx} className={`seamless-week-allday-event seamless-color-${color}-bg`}
                      onClick={() => navigateToEvent(e.slug || createEventSlug(e.title, e.id), e.is_group_event)}>
                   {e.title}
                 </div>
              );
            })}
         </div>
      </div>

      <div className="seamless-week-grid-body">
         <div className="seamless-week-time-gutter">
            {hours.map(hour => {
               if (hour === 0) return <div key={hour} className="seamless-week-hour-label empty"></div>;
               const displayHour = hour > 12 ? hour - 12 : hour;
               const ampm = hour >= 12 ? 'pm' : 'am';
               return <div key={hour} className="seamless-week-hour-label">{displayHour} {ampm}</div>;
            })}
         </div>

         <div className="seamless-week-columns-container single-day-mode">
            <div className="seamless-week-bg-lines">
               {hours.map(hour => <div key={hour} className="seamless-week-bg-line"></div>)}
            </div>
            
            <div className="seamless-week-columns">
                 <div className="seamless-week-day-col">
                    {dayEvents.map((block, eIdx) => {
                       const color = getCategoryColor(block.event);
                       return (
                         <div 
                           key={eIdx}
                           className={`seamless-week-event-block seamless-color-${color}-bg`}
                           style={{
                              top: `${block.top}px`,
                              height: `max(${block.height}px, 20px)`
                           }}
                           onClick={() => navigateToEvent(block.event?.slug || createEventSlug(block.event?.title, block.event?.id), block.event?.is_group_event)}
                         >
                           <span className={`seamless-week-event-dot seamless-color-${color}-border`}></span>
                           <span className="seamless-week-event-title-span">{block.event?.title}</span>
                         </div>
                       )
                    })}
                 </div>
            </div>
         </div>
      </div>
    </div>
  );
};
