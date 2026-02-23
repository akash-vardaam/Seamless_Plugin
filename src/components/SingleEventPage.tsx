import React, { useState, useEffect } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Calendar, Clock, MapPin, Users, Check } from 'lucide-react';
import { useSingleEvent } from '../hooks/useSingleEvent';
import { SeamlessAccordion } from './SeamlessAccordion';

import type { Event } from '../types/event';
import { LoadingSpinner } from './LoadingSpinner';

export const SingleEventPage: React.FC = () => {
    const { slug: paramSlug } = useParams<{ slug: string }>();
    // Logic to determine slug: param > DOM attribute
    const [slug, setSlug] = useState<string>(paramSlug || '');
    const [calendarDropdownOpen, setCalendarDropdownOpen] = useState(false);
    const [isCalendarAdded, setIsCalendarAdded] = useState(false);
    const calendarDropdownRef = React.useRef<HTMLDivElement>(null);

    const handleCalendarOptionClick = () => {
        setCalendarDropdownOpen(false);
        setIsCalendarAdded(true);
        setTimeout(() => setIsCalendarAdded(false), 3000);
    };

    useEffect(() => {
        if (!paramSlug) {
            const domSlug = document.getElementById('event_detail')?.getAttribute('data-event-slug');
            if (domSlug) {
                setSlug(domSlug);
            }
        }
    }, [paramSlug]);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (calendarDropdownRef.current && !calendarDropdownRef.current.contains(event?.target as Node)) {
                setCalendarDropdownOpen(false);
            }
        };

        if (calendarDropdownOpen) {
            document.addEventListener('mousedown', handleClickOutside);
        } else {
            document.removeEventListener('mousedown', handleClickOutside);
        }

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
        };
    }, [calendarDropdownOpen]);

    const isGroupEvent = window.location.pathname.includes('/group-event/');
    const { event, loading, error } = useSingleEvent(slug, isGroupEvent);

    // Date/Time Formatters
    const formatEventDateRange = (startStr: string, endStr: string) => {
        if (!startStr) return '';
        try {
            const start = new Date(startStr);
            const end = endStr ? new Date(endStr) : start;

            const isSameDay = start.toDateString() === end.toDateString();
            const startWeekday = start.toLocaleDateString('en-US', { weekday: 'long' });
            const startMonth = start.toLocaleDateString('en-US', { month: 'long' });
            const startDay = start.getDate();
            const startYear = start.getFullYear();

            if (isSameDay) {
                return `${startWeekday}, ${startMonth} ${startDay}, ${startYear}`;
            }

            const endWeekday = end.toLocaleDateString('en-US', { weekday: 'long' });
            
            // Check if same month
            if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
                const endDay = end.getDate();
                return `${startWeekday} - ${endWeekday}, ${startMonth} ${startDay} - ${endDay}, ${startYear}`;
            }
            // If different month but same year
            if (start.getFullYear() === end.getFullYear()) {
                 const endMonth = end.toLocaleDateString('en-US', { month: 'long' });
                 const endDay = end.getDate();
                 return `${startWeekday} - ${endWeekday}, ${startMonth} ${startDay} - ${endMonth} ${endDay}, ${startYear}`;
            }
            
            return `${start.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'})} - ${end.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'})}`;
        } catch {
            return startStr;
        }
    };
    
    // For specific date mapping logic later on in schedule where we just need "Mar 04, 2026"
    const getFormattedDate = (dateStr: string) => {
        if (!dateStr) return '';
        try {
            return new Date(dateStr).toLocaleDateString('en-US', {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
        } catch {
            return dateStr;
        }
    };


    const getFormattedTimeRange = (startStr: string, endStr: string) => {
        if (!startStr) return '';
        try {
            const options: Intl.DateTimeFormatOptions = {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true,
                timeZone: 'America/Chicago' // Enforce timezone
            };
            const start = new Date(startStr).toLocaleTimeString('en-US', options);
            const end = endStr ? new Date(endStr).toLocaleTimeString('en-US', options) : '';
            return `${start}${end ? ` – ${end}` : ''} CDT`;
        } catch {
            return '';
        }
    };


    // Date/Time Helper for Calendar Links
    const formatCalendarDate = (dateStr: string) => {
        return new Date(dateStr).toISOString().replace(/-|:|\.\d\d\d/g, "");
    };

    const getCalendarDetails = (evt: Event) => {
        const title = encodeURIComponent(evt.title);
        const details = encodeURIComponent(evt.description.replace(/<[^>]+>/g, ''));
        const location = encodeURIComponent(`${evt.venue?.name}, ${evt.venue?.address_line_1}, ${evt.venue?.city}, ${evt.venue?.state} ${evt.venue?.zip_code}`);
        const start = formatCalendarDate(evt.start_date);
        const end = formatCalendarDate(evt.end_date);
        return { title, details, location, start, end };
    };

    const generateICSFile = (evt: Event) => {
        const { start, end } = getCalendarDetails(evt);
        const description = evt.description.replace(/<[^>]+>/g, '');
        const location = `${evt.venue?.name}, ${evt.venue?.address_line_1}, ${evt.venue?.city}, ${evt.venue?.state} ${evt.venue?.zip_code}`;

        const icsContent = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'BEGIN:VEVENT',
            `DTSTART:${start}`,
            `DTEND:${end}`,
            `SUMMARY:${evt.title}`,
            `DESCRIPTION:${description}`,
            `LOCATION:${location.replace(/,/g, '\\,')}`,
            'END:VEVENT',
            'END:VCALENDAR'
        ].join('\n');

        const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });
        return URL.createObjectURL(blob);
    };

    const getYahooCalendarLink = (evt: Event) => {
        const { title, details, location, start, end } = getCalendarDetails(evt);
        return `https://calendar.yahoo.com/?v=60&view=d&type=20&title=${title}&st=${start}&et=${end}&desc=${details}&in_loc=${location}`;
    };

    const getOutlookCalendarLink = (evt: Event) => {
        const { title, details, location, start, end } = getCalendarDetails(evt);
        return `https://outlook.live.com/calendar/0/deeplink/compose?path=/calendar/action/compose&rru=addevent&startdt=${start}&enddt=${end}&subject=${title}&body=${details}&location=${location}`;
    };

    const getOffice365CalendarLink = (evt: Event) => {
        const { title, details, location, start, end } = getCalendarDetails(evt);
        return `https://outlook.office.com/calendar/0/deeplink/compose?path=/calendar/action/compose&rru=addevent&startdt=${start}&enddt=${end}&subject=${title}&body=${details}&location=${location}`;
    };

    const getGoogleCalendarLink = (evt: Event) => {
        try {
            const startDate = new Date(evt.start_date).toISOString().replace(/-|:|\.\d\d\d/g, "");
            const endDate = new Date(evt.end_date).toISOString().replace(/-|:|\.\d\d\d/g, "");
            const title = encodeURIComponent(evt.title);
            const details = encodeURIComponent(evt.description.replace(/<[^>]+>/g, '')); // Strip HTML for cal desc
            const location = encodeURIComponent(`${evt.venue?.name}, ${evt.venue?.address_line_1}, ${evt.venue?.city}, ${evt.venue?.state} ${evt.venue?.zip_code}`);

            return `https://www.google.com/calendar/render?action=TEMPLATE&text=${title}&dates=${startDate}/${endDate}&details=${details}&location=${location}&ctz=America/Chicago`;
        } catch {
            return '#';
        }
    };

    if (loading) {
        return (
            <div className="seamless-single-event-container" style={{ minHeight: '50vh', display: 'flex', justifyContent: 'center', alignItems: 'center' }}>
                <LoadingSpinner fullHeight={false} />
            </div>
        );
    }

    if (error || !event) {
        return (
            <div className="seamless-single-event-container">
                <div className="seamless-error-container">
                    <p className="seamless-error-title">Event not found</p>
                    <Link to="/" className="seamless-btn-outline-primary seamless-single-evt-back-btn">Back to Events</Link>
                </div>
            </div>
        );
    }

    // Prepare Sections
    const sections: { title: string; content: React.ReactNode }[] = [];


    const isMultiDayEvent = getFormattedDate(event?.start_date) !== getFormattedDate(event?.end_date);

    // 1. Schedule Section
    if (event?.schedules && event?.schedules.length > 0) {
        sections.push({
            title: 'Schedule',
            content: (
                <table className="event-schedule-table">
                    <thead>
                        <tr>
                            <th>{isMultiDayEvent ? 'DATE & TIME' : 'TIME'}</th>
                            <th>DESCRIPTION</th>
                        </tr>
                    </thead>
                    <tbody>
                        {event?.schedules.map((sch, idx) => {
                            // Helper to parse "Mar 04, 2026 12:00 PM" -> { date: "Mar 04", year: "2026", time: "12:00 PM", fullDate: "Mar 04, 2026" }
                            // Fallback using split if regex fails
                            const parseDateStr = (str: string) => {
                                if (!str) return { date: '', year: '', time: '', fullDate: '' };
                                const match = str.match(/^([A-Za-z]+\s\d+),\s(\d{4})\s(.*)$/);
                                if (match) {
                                    return { date: match[1], year: match[2], time: match[3], fullDate: `${match[1]}, ${match[2]}` };
                                }
                                // Fallback logic if format is different
                                return { date: str, year: '', time: '', fullDate: str };
                            };

                            const schStart = parseDateStr(sch?.start_date_display);
                            const schEnd = parseDateStr(sch?.end_date_display);
                            // Use formatted_start_date from event, or fallback to start_date
                            const mainStart = parseDateStr(event?.formatted_start_date || event?.start_date);

                            const isSameAsMainDate = schStart.fullDate === mainStart.fullDate;

                            // Check previous row to see if date changed
                            let isNewDateGroup = true;
                            if (idx > 0) {
                                const prevSchStart = parseDateStr(event?.schedules[idx - 1].start_date_display);
                                if (prevSchStart.fullDate === schStart.fullDate) {
                                    isNewDateGroup = false;
                                }
                            }

                            // Comparison Logic for Display
                            let displayString = '';
                            const isItemMultiDay = schStart.fullDate !== schEnd.fullDate;

                            if (isItemMultiDay) {
                                displayString = `${schStart.date} ${schStart.time} – ${schEnd.date} ${schEnd.time}`;
                            } else {
                                const timeRange = `${schStart.time} – ${schEnd.time}`;
                                if (isSameAsMainDate) {
                                    displayString = timeRange;
                                } else {
                                    // Date is different from main event.
                                    if (isNewDateGroup) {
                                        displayString = `${schStart.date} ${timeRange}`;
                                    } else {
                                        // Same date as previous row (but different from main event).
                                        // Standard table approach: Don't repeat date.
                                        displayString = timeRange;
                                    }
                                }
                            }

                            return (
                                <tr key={idx}>
                                    <td>{displayString}</td>
                                    <td dangerouslySetInnerHTML={{ __html: sch?.description }} />
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            )
        });
    }

    // 2. Additional Details Sections
    if (event?.additional_details && event?.additional_details.length > 0) {
        event?.additional_details.forEach(detail => {
            sections.push({
                title: detail?.name,
                content: <div dangerouslySetInnerHTML={{ __html: detail?.value }} />
            });
        });
    }

    // Fallback/Legacy Sections if needed (Optional based on requirement strictness)
    // The prompt implies "Loop through data.additional_details", so likely we only use that + Schedule.
    // I will rely on the explicit instruction.

    const computedCapacity = event?.capacity || (event?.tickets && event?.tickets.length > 0 ? event?.tickets[0].inventory : null);
    
    // Choose which date boundaries to use
    const startDateToUse = isGroupEvent && event?.event_date_range?.start ? event?.event_date_range.start : event?.start_date;
    const endDateToUse = isGroupEvent && event?.event_date_range?.end ? event?.event_date_range.end : event?.end_date;

    // Check if event has passed
    const isEventPassed = new Date(endDateToUse || startDateToUse).getTime() < new Date().getTime();

    return (
        <article className="seamless-single-event-container">
            {/* Breadcrumbs */}
            {/* ... breadcrumbs commented out ... */}

            <div className="seamless-single-event-grid">
                {/* Header - Moved out for mobile/tab ordering */}
                <div className="seamless-event-header-wrapper">
                <header className="seamless-event-header-group">
                    <div className="seamless-event-icon-circle">
                        {event?.featured_image ? (
                            <img src={event?.featured_image} alt="Event Icon" />
                        ) : (
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#1a365d" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                                <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                                <line x1="12" y1="19" x2="12" y2="23"></line>
                                <line x1="8" y1="23" x2="16" y2="23"></line>
                            </svg>
                        )}
                    </div>
                    <h1 className="seamless-event-title">{event?.title}</h1>
                </header>

                {/* Left Column Content (Description + Accordions) */}
                <section className="seamless-single-event-content">
                    {/* Description */}
                    <div  className="seamless-event-description" dangerouslySetInnerHTML={{ __html: event?.description }}></div>


                    {/* Accordions */}
                    <SeamlessAccordion items={sections} />
                </section>
                </div>

                {/* Right Column: Sidebar */}
                <aside className="seamless-single-event-sidebar">
                    {/* Details Box */}
                    <section className="seamless-sidebar-box seamless-details-box">
                        <ul className="seamless-single-evt-details-list">
                            <li className="seamless-detail-row">
                                <Calendar className="seamless-detail-icon" size={20} />
                                <div>
                                    <span className="seamless-detail-label">Date</span>
                                    <p className="seamless-detail-value">{formatEventDateRange(startDateToUse, endDateToUse)}</p>
                                </div>
                            </li>
                            <li className="seamless-detail-row">
                                <Clock className="seamless-detail-icon" size={20} />
                                <div>
                                    <span className="seamless-detail-label">Time</span>
                                    <p className="seamless-detail-value">{getFormattedTimeRange(startDateToUse, endDateToUse)}</p>
                                </div>
                            </li>
                            {computedCapacity !== null && (
                                <li className="seamless-detail-row">
                                    <Users className="seamless-detail-icon" size={20} />
                                    <div className="seamless-single-evt-full-width">
                                        <span className="seamless-detail-label">Capacity</span>
                                        <p className="seamless-detail-value">{computedCapacity} capacity</p>
                                    </div>
                                </li>
                            )}
                            <li className="seamless-detail-row">
                                <MapPin className="seamless-detail-icon" size={20} />
                                <div>
                                    <span className="seamless-detail-label">Location</span>
                                    {event?.venue?.name && (
                                        <p className="seamless-detail-value">
                                            {event?.venue?.google_map_url ? (
                                                <a href={event?.venue.google_map_url} target="_blank" rel="noopener noreferrer" className="seamless-location-link" style={{ color: '#0ea5e9', textDecoration: 'none', display: 'flex', alignItems: 'center', gap: '4px' }}>
                                                    {event?.venue.name}
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                                </a>
                                            ) : (
                                                <span>{event?.venue.name}</span>
                                            )}
                                        </p>
                                    )}
                                    {(event?.venue?.address_line_1 || event?.venue?.city) && (
                                        <div className="seamless-detail-subvalue" style={{ lineHeight: '1.4', marginTop: '4px', display: 'flex', flexDirection: 'column' }}>
                                            <span>{event?.venue.address_line_1 ? `${event?.venue.address_line_1} ${event?.venue.name || ''},` : ''}</span>
                                            <span>{event?.venue.city ? `(${event?.venue.city}, ${event?.venue.state})` : ''}</span>
                                        </div>
                                    )}
                                </div>
                            </li>
                        </ul>

                        <div className="seamless-single-evt-dropdown-wrap" ref={calendarDropdownRef}>

                            <button
                                onClick={() => setCalendarDropdownOpen(!calendarDropdownOpen)}
                                className="seamless_single_page_calendar_button"
                            >
                                Add to Calendar
                                {isCalendarAdded && (
                                    <div className="seamless-calendar-check-icon">
                                        <Check size={20} strokeWidth={3} />
                                    </div>
                                )}
                            </button>

                            {calendarDropdownOpen && (
                                <div className="seamless-calendar-dropdown">
                                    <a href={generateICSFile(event)} download={`${event?.slug}.ics`} className="seamless-calendar-option" onClick={handleCalendarOptionClick}>
                                        <svg viewBox="0 0 24 24" width="20" height="20" className="seamless-cal-icon"><path fill="currentColor" d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.14.47-2.17.65-3.32-.23C3.62 17.5 3 10.3 6.96 10.1c1.28-.06 2.49.56 3.3.56.81 0 2.2-.6 3.6-.5 1.5.06 2.65.6 3.4 1.7-2.9 1.7-2.4 6 1.1 7.4-.7 1.75-1.07 1.25-1.31 1.02zM13 6.3c.6 1.7-1.5 3.3-3.2 2.7-1.3-.4-1.7-2.2-.8-3.5.9-1.2 3.4-1.1 4 .8z" /></svg>
                                        Apple
                                    </a>
                                    <a href={getGoogleCalendarLink(event)} target="_blank" rel="noopener noreferrer" className="seamless-calendar-option" onClick={handleCalendarOptionClick}>
                                        <svg viewBox="0 0 24 24" width="20" height="20" className="seamless-cal-icon"><path fill="currentColor" d="M20 2h-4v2h2v2h2V4h2V2h-2zm-6 0h-2v2h2V2zM7 5v2H5V5H2v2h3v2l0 0H2v2h3v14h14v-3h-2v1H7V11h12v-2H7V7h12V5H7z" /></svg>
                                        Google
                                    </a>
                                    <a href={generateICSFile(event)} download={`${event?.slug}.ics`} className="seamless-calendar-option" onClick={handleCalendarOptionClick}>
                                        <svg viewBox="0 0 24 24" width="20" height="20" className="seamless-cal-icon"><rect x="3" y="4" width="18" height="18" rx="2" ry="2" fill="none" stroke="currentColor" strokeWidth="2" /><line x1="16" y1="2" x2="16" y2="6" stroke="currentColor" strokeWidth="2" /><line x1="8" y1="2" x2="8" y2="6" stroke="currentColor" strokeWidth="2" /><line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" strokeWidth="2" /></svg>
                                        iCal File
                                    </a>
                                    <a href={getOffice365CalendarLink(event)} target="_blank" rel="noopener noreferrer" className="seamless-calendar-option" onClick={handleCalendarOptionClick}>
                                        <svg viewBox="0 0 24 24" width="20" height="20" className="seamless-cal-icon"><path fill="#D83B01" d="M12.5 13v9H4l-2-3V4l2-2h8.5v11z" /><path fill="#A4373A" d="M12.5 2h9l2 2v6h-11V2z" /><path fill="#7E1E34" d="M12.5 13h11l-2 3v4l2 2H12.5v-9z" /></svg>
                                        Microsoft 365
                                    </a>
                                    <a href={getOutlookCalendarLink(event)} target="_blank" rel="noopener noreferrer" className="seamless-calendar-option" onClick={handleCalendarOptionClick}>
                                        <svg viewBox="0 0 24 24" width="20" height="20" className="seamless-cal-icon"><path fill="#0072C6" d="M1 18l.8 2.2L4 21h15l2-3V6l-2-3H4L1 4v14z" /><path fill="#F2F2F2" d="M15 13h-3v3h3v-3zm0-4h-3v3h3V9zM9 13H6v3h3v-3zm0-4H6v3h3V9z" /></svg>
                                        Outlook.com
                                    </a>
                                    <a href={getYahooCalendarLink(event)} target="_blank" rel="noopener noreferrer" className="seamless-calendar-option" onClick={handleCalendarOptionClick}>
                                        <svg viewBox="0 0 24 24" width="20" height="20" className="seamless-cal-icon"><path fill="#6001D2" d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm1.2 13h-2.4l-.6 2H8l3-8h2l3 8h-2.2l-.6-2z" /></svg>
                                        Yahoo
                                    </a>
                                </div>
                            )}
                        </div>
                    </section>

                    {/* Tickets Box */}
                    <section className="seamless-sidebar-box seamless-tickets-box">

                        <h3 className="seamless-tickets-title">Tickets</h3>
                        
                        {/* Normal Event Tickets */}
                        {!isGroupEvent && event?.tickets && event?.tickets.map(ticket => (
                            <div key={ticket?.id} className="seamless-ticket-item">
                                <div className="seamless-ticket-row">
                                    <span className="seamless-ticket-name">{ticket?.label}</span>
                                    <span className="seamless-ticket-price">{ticket?.price === 0 ? 'Free' : `$${ticket?.price}`}</span>
                                </div>
                                <span className="seamless-ticket-deadline">
                                    Registration ends on {ticket?.formatted_registration_end_date}
                                </span>
                            </div>
                        ))}

                        {/* Group Event Tickets from Associated Events */}
                        {isGroupEvent && event?.associated_events && event?.associated_events.map(assocEvent => (
                            <div key={assocEvent.id} className="seamless-ticket-item">
                                <div className="seamless-ticket-row">
                                    <span className="seamless-ticket-name">{assocEvent.title}</span>
                                    {assocEvent.price !== undefined ? (
                                        <span className="seamless-ticket-price">{assocEvent.price == 0 ? 'Free' : `$${assocEvent.price}`}</span>
                                    ) : null}
                                </div>
                            </div>
                        ))}

                        {isEventPassed ? (
                            <div className="seamless-event-passed-box">
                                Event has passed!
                            </div>
                        ) : event?.registration_url ? (
                            <a
                                href={event?.registration_url}
                                className="event-register-btn"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Register Now
                            </a>
                        ) : null}
                    </section>
                </aside>
            </div>
        </article>
    );
};
