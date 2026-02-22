import React, { useState, useEffect, useMemo } from 'react';
import type { Event } from '../../types/event';
import { getCategoryColor, extractDateOnly } from './utils';
import { navigateToEvent, createEventSlug } from '../../utils/urlHelper';

const formatTimeRange = (startDate: string, endDate: string): string => {
  try {
    const start = new Date(startDate);
    return start.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
  } catch {
    return '';
  }
};

interface MonthViewProps {
  currentDate: Date;
  events: Event[];
}

export const MonthView: React.FC<MonthViewProps> = ({ currentDate, events }) => {
  const [selectedDay, setSelectedDay] = useState<Date | null>(null);

  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      const target = e.target as Element;
      if (!target.closest('.seamless-month-cell') && !target.closest('.seamless-month-popup')) {
        setSelectedDay(null);
      }
    };
    document.addEventListener('click', handleClickOutside);
    return () => document.removeEventListener('click', handleClickOutside);
  }, []);

  const getCalendarDays = (date: Date) => {
    const year = date.getFullYear();
    const month = date.getMonth();
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();

    const days = [];
    for (let i = firstDay - 1; i >= 0; i--) {
      days.push({ date: new Date(year, month - 1, daysInPrevMonth - i), isCurrentMonth: false });
    }
    for (let i = 1; i <= daysInMonth; i++) {
      days.push({ date: new Date(year, month, i), isCurrentMonth: true });
    }
    const remainingDays = 42 - days.length;
    for (let i = 1; i <= remainingDays; i++) {
      days.push({ date: new Date(year, month + 1, i), isCurrentMonth: false });
    }
    return days;
  };

  const daysArr = getCalendarDays(currentDate);

  const weeks = useMemo(() => {
    const w = [];
    for (let i = 0; i < daysArr.length; i += 7) {
      w.push(daysArr.slice(i, i + 7));
    }
    return w;
  }, [currentDate]);

  const normalizedEvents = useMemo(() => {
    return events.map(e => {
       const start = new Date(extractDateOnly(e.start_date));
       const end = e.end_date ? new Date(extractDateOnly(e.end_date)) : new Date(start);
       start.setHours(0,0,0,0);
       end.setHours(23,59,59,999);
       return { ...e, rawStart: start, rawEnd: end };
    });
  }, [events]);

  const handleDayClick = (dayDate: Date) => {
    if (selectedDay && selectedDay.toDateString() === dayDate.toDateString()) {
      setSelectedDay(null);
    } else {
      setSelectedDay(dayDate);
    }
  };

  const todayStr = new Date().toDateString();
  const dayHeaders = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];

  return (
    <div className="seamless-month-view">
      <div className="seamless-month-rows-container">
        
        <div className="seamless-month-header-row">
          {dayHeaders.map((dh, i) => (
             <div key={'dh'+i} className="seamless-month-header-cell">{dh}</div>
          ))}
        </div>

        {weeks.map((weekDays, wIdx) => {
          const wStart = weekDays[0].date.getTime();
          const wEnd = weekDays[6].date.getTime();
          
          const weekEvents = normalizedEvents.filter(e => {
             return e.rawStart.getTime() <= wEnd && e.rawEnd.getTime() >= wStart;
          });

          weekEvents.sort((a,b) => {
            const lenA = a.rawEnd.getTime() - a.rawStart.getTime();
            const lenB = b.rawEnd.getTime() - b.rawStart.getTime();
            if (lenA !== lenB) return lenB - lenA; // multi-day first
            return a.rawStart.getTime() - b.rawStart.getTime();
          });

          const rowOccupancy: boolean[][] = [];
          const layouts: any[] = [];

          weekEvents.forEach((e) => {
            let startCol = weekDays.findIndex(wd => wd.date.toDateString() === e.rawStart.toDateString());
            let endCol = weekDays.findIndex(wd => wd.date.toDateString() === e.rawEnd.toDateString());
            
            if (e.rawStart.getTime() < wStart) startCol = 0;
            if (e.rawEnd.getTime() > wEnd) endCol = 6;
            if (startCol === -1) startCol = 0;
            if (endCol === -1) endCol = 6;
            
            let level = 0;
            while(true) {
              if (!rowOccupancy[level]) rowOccupancy[level] = new Array(7).fill(false);
              let conflict = false;
              for(let i=startCol; i<=endCol; i++){
                if (rowOccupancy[level][i]) { conflict = true; break; }
              }
              if (!conflict) {
                for(let i=startCol; i<=endCol; i++){
                  rowOccupancy[level][i] = true;
                }
                break;
              }
              level++;
            }
            layouts.push({ event: e, startCol, endCol, level });
          });

          return (
             <div key={wIdx} className="seamless-month-week-row">
                {/* 1. Background Grid */}
                <div className="seamless-month-week-bg">
                   {weekDays.map((dayObj, dIdx) => {
                      const dateKey = dayObj.date.toDateString();
                      const isToday = dateKey === todayStr;
                      
                      const singleDayEvts = weekEvents.filter(e => 
                         e.rawStart.toDateString() === dateKey || 
                         e.rawEnd.toDateString() === dateKey || 
                         (e.rawStart.getTime() <= dayObj.date.getTime() && e.rawEnd.getTime() >= dayObj.date.getTime())
                      );

                      return (
                         <div 
                           key={dIdx} 
                           className={`seamless-month-cell ${!dayObj.isCurrentMonth ? 'inactive' : ''} ${isToday ? 'today' : ''} ${dIdx === 0 ? 'sunday-col' : ''}`}
                           onClick={() => handleDayClick(dayObj.date)}
                         >
                            <div className="seamless-month-cell-header">
                              <span className="seamless-month-date-num">
                                {String(dayObj.date.getDate()).padStart(2, '0')}
                              </span>
                            </div>

                            <div className="seamless-month-events-mobile">
                              {singleDayEvts.map((e, eIdx) => {
                                if (eIdx >= 4) return null;
                                const c = getCategoryColor(e);
                                return <span key={eIdx} className={`seamless-mobile-dot seamless-color-${c}-border`}></span>;
                              })}
                            </div>

                            {selectedDay && selectedDay.toDateString() === dateKey && singleDayEvts.length > 0 && (
                              <div className="seamless-month-popup" onClick={(e) => e.stopPropagation()}>
                                <div className="seamless-popup-header">{dayObj.date.toISOString().split('T')[0]}</div>
                                <div className="seamless-popup-body">
                                  {singleDayEvts.map((pe, eIdx) => {
                                    const c = getCategoryColor(pe);
                                    return (
                                      <div 
                                        key={'pop'+pe.id+eIdx} 
                                        className={`seamless-popup-event seamless-color-${c}-border`}
                                        onClick={() => navigateToEvent(pe.slug || createEventSlug(pe.title, pe.id), pe.is_group_event)}
                                      >
                                        <span className="seamless-popup-title">{pe.title}</span>
                                        <span className="seamless-popup-time">{formatTimeRange(pe.start_date, pe.end_date || pe.start_date)}</span>
                                      </div>
                                    );
                                  })}
                                </div>
                              </div>
                            )}
                         </div>
                      );
                   })}
                </div>

                {/* 2. Absolute Events Layer (Desktop Matrix) */}
                <div className="seamless-month-events-layer">
                   {layouts.map((l, lIdx) => {
                      const color = getCategoryColor(l.event);
                      const isMulti = l.endCol > l.startCol;
                      if (l.level > 2) return null;

                      return (
                        <div 
                           key={lIdx}
                           className={`seamless-month-abs-event seamless-color-${color}-bg`}
                           style={{
                              gridColumn: `${l.startCol + 1} / span ${l.endCol - l.startCol + 1}`,
                              gridRow: `${l.level + 1}`,
                              borderRadius: isMulti ? '4px' : '4px',
                              borderLeftWidth: '3px',
                              borderLeftStyle: 'solid',
                              padding: '2px 6px',
                              fontSize: '10px',
                              fontWeight: '600',
                              margin: '2px 4px',
                              cursor: 'pointer',
                              overflow: 'hidden',
                              whiteSpace: 'nowrap',
                              textOverflow: 'ellipsis',
                              boxSizing: 'border-box'
                           }}
                           onClick={(evt) => {
                             evt.stopPropagation();
                             navigateToEvent(l.event?.slug || createEventSlug(l.event?.title, l.event?.id), l.event?.is_group_event);
                           }}
                        >
                           <span className={`seamless-month-popup-dot seamless-color-${color}-border`} style={{ display: 'inline-block', marginRight:'4px' }}></span>
                           {l.event?.title}
                        </div>
                      );
                   })}
                   
                   {weekDays.map((dayObj, dIdx) => {
                     const hidden = layouts.filter(l => l.startCol <= dIdx && l.endCol >= dIdx && l.level > 2).length;
                     if (hidden === 0) return null;
                     return (
                        <div 
                          key={`more${dIdx}`}
                          style={{
                             gridColumn: `${dIdx + 1} / span 1`,
                             gridRow: `4`, // level limit
                             fontSize: '10px',
                             fontWeight: '600',
                             color: '#64748b',
                             textAlign: 'left',
                             paddingLeft: '6px',
                             paddingTop: '2px',
                             cursor: 'pointer',
                             whiteSpace: 'nowrap',
                             zIndex: 10
                          }}
                          onClick={() => handleDayClick(dayObj.date)}
                        >
                           {hidden} MORE...
                        </div>
                     );
                   })}
                </div>
             </div>
          );
        })}
      </div>
    </div>
  );
};
