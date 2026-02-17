import React from 'react';

interface DayHeaderProps {
  abbreviated?: boolean;
}

export const CalendarDayHeaders: React.FC<DayHeaderProps> = ({ abbreviated = false }) => {
  const days = abbreviated ? ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'] : 
               ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  
  return (
    <div className="seamless-calendar-day-headers">
      {days.map((day) => (
        <div
          key={day}
          className="seamless-calendar-day-header"
        >
          {day}
        </div>
      ))}
    </div>
  );
};
