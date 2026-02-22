import React, { useMemo } from 'react';
import type { Event } from '../../types/event';
import { getCategoryColor } from './utils';
import { navigateToEvent, createEventSlug } from '../../utils/urlHelper';

interface WeekViewProps {
  currentDate: Date;
  events: Event[];
}

const HOUR_HEIGHT = 60;

export const WeekView: React.FC<WeekViewProps> = ({ currentDate, events }) => {
  const getWeekDates = (date: Date) => {
    const day = date.getDay();
    const diff = date.getDate() - day;
    const sunday = new Date(date.setDate(diff));
    const dates = [];
    for (let i = 0; i < 7; i++) {
      const d = new Date(sunday);
      d.setDate(sunday.getDate() + i);
      dates.push(d);
    }
    return dates;
  };

  const weekDates = getWeekDates(new Date(currentDate));
  const isCurrentWeek = weekDates.some(d => d.toDateString() === new Date().toDateString());

  // Hours: 1 AM to 12 PM to 11 PM
  const hours = Array.from({length: 24}, (_, i) => i);

  // Group events
  const dayColumns = useMemo(() => {
    const cols = weekDates.map(() => [] as any[]);

    events.forEach(e => {
      try {
        const startRaw = new Date(e.start_date);
        let endRaw = e.end_date ? new Date(e.end_date) : new Date(startRaw.getTime() + 60*60*1000); // default 1hr if no end
        
        weekDates.forEach((d, colIndex) => {
          const dayStart = new Date(d);
          dayStart.setHours(0, 0, 0, 0);
          const dayEnd = new Date(d);
          dayEnd.setHours(23, 59, 59, 999);

          // Overlap check
          if (startRaw <= dayEnd && endRaw >= dayStart && startRaw.getTime() !== endRaw.getTime()) {
            const blockStart = new Date(Math.max(startRaw.getTime(), dayStart.getTime()));
            const blockEnd = new Date(Math.min(endRaw.getTime(), dayEnd.getTime() + 1));
            
            let startHour = blockStart.getHours() + blockStart.getMinutes() / 60;
            let endHour = blockEnd.getHours() + blockEnd.getMinutes() / 60;
            
            if (blockEnd.getHours() === 0 && blockEnd.getMinutes() === 0 && blockEnd.getTime() > blockStart.getTime()) {
               endHour = 24; // Crosses exact midnight
            }
            if (endHour <= startHour) {
               endHour = startHour + 1;
            }

            const top = startHour * HOUR_HEIGHT;
            const height = (endHour - startHour) * HOUR_HEIGHT;
            
            cols[colIndex].push({
              event: e,
              top,
              height,
            });
          }
        });
      } catch(err) { console.error(err); }
    });
    
    // Compute overlaps for each column
    cols.forEach(col => {
      col.sort((a, b) => a.top - b.top || b.height - a.height);
      const groups: any[][] = [];
      let lastEventEnd = 0;
      col.forEach(block => {
        if (block.top >= lastEventEnd) {
          groups.push([block]);
          lastEventEnd = block.top + block.height;
        } else {
          groups[groups.length - 1].push(block);
          lastEventEnd = Math.max(lastEventEnd, block.top + block.height);
        }
      });
      
      groups.forEach(group => {
        const columns: any[][] = [];
        group.forEach(block => {
          let placed = false;
          for (let i = 0; i < columns.length; i++) {
            const lastInCol = columns[i][columns[i].length - 1];
            if (lastInCol.top + lastInCol.height <= block.top) {
              columns[i].push(block);
              placed = true;
              break;
            }
          }
          if (!placed) columns.push([block]);
        });
        
        columns.forEach((colArray, i) => {
          colArray.forEach(block => {
            block.left = (i / columns.length) * 100;
            block.width = 100 / columns.length;
          });
        });
      });
    });

    return { cols };
  }, [events, weekDates]);

  return (
    <div className="seamless-week-view">
      <div className="seamless-week-header-row">
        <div className="seamless-week-time-gutter">
           <div className="seamless-week-allday-label">Time</div>
        </div>
        <div className="seamless-week-days">
          {weekDates.map((d, i) => (
            <div key={i} className={`seamless-week-day-header ${d.toDateString() === new Date().toDateString() ? 'today' : ''} ${i===0 && !isCurrentWeek ? 'sunday' : ''}`}>
              <span className="seamless-week-date-num">{d.getDate()}</span>
              <span className="seamless-week-day-name">{d.toLocaleDateString('en-US', { weekday: 'short' }).toUpperCase()}</span>
            </div>
          ))}
        </div>
      </div>

      {/* GRID BODY */}
      <div className="seamless-week-grid-body">
         <div className="seamless-week-time-gutter">
            {hours.map(hour => {
               if (hour === 0) return <div key={hour} className="seamless-week-hour-label empty"></div>;
               const displayHour = hour > 12 ? hour - 12 : hour;
               const ampm = hour >= 12 ? 'pm' : 'am';
               return <div key={hour} className="seamless-week-hour-label">{displayHour} {ampm}</div>;
            })}
         </div>

         <div className="seamless-week-columns-container">
            {/* Background Lines */}
            <div className="seamless-week-bg-lines">
               {hours.map(hour => <div key={hour} className="seamless-week-bg-line"></div>)}
            </div>
            
            {/* Day Columns containing absolute events */}
            <div className="seamless-week-columns">
               {dayColumns.cols.map((colEvents, colIdx) => (
                 <div key={colIdx} className="seamless-week-day-col">
                    {colEvents.map((block, eIdx) => {
                       const color = getCategoryColor(block.event);
                       return (
                         <div 
                           key={eIdx}
                           className={`seamless-week-event-block seamless-color-${color}-bg`}
                           style={{
                              top: `${block.top}px`,
                              height: `calc(${block.height}px)`,
                              left: `calc(${block.left}% + 2px)`,
                              width: `calc(${block.width}% - 4px)`,
                           }}
                           onClick={() => navigateToEvent(block.event?.slug || createEventSlug(block.event?.title, block.event?.id), block.event?.is_group_event)}
                         >
                           <span className={`seamless-week-event-dot seamless-color-${color}-border`}></span>
                           <span className="seamless-week-event-title-span">{block.event?.title}</span>
                         </div>
                       )
                    })}
                 </div>
               ))}
            </div>
         </div>
      </div>
    </div>
  );
};
