export const getCategoryColor = (
  event: any
): string => {
  const COLOR_PALETTES = [
    'amber',
    'red',
    'indigo',
    'green',
    'purple',
    'orange',
    'pink',
    'blue',
    'teal',
    'slate'
  ];

  const seedString = String(event?.id || '') + String(event?.title || '') + String(event?.start_date || '') || 'default';
  let hash = 0;
  for (let i = 0; i < seedString.length; i++) {
    hash = seedString.charCodeAt(i) + ((hash << 5) - hash);
  }
  
  const index = Math.abs(hash) % COLOR_PALETTES.length;
  return COLOR_PALETTES[index];
};

export const getStartTime = (dateString: string) => {
  const date = new Date(dateString);
  return date.toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  });
};

export const extractDateOnly = (dateTimeString: string): string => {
  // Handle format like "Mar 04, 2026 12:00 PM" or "Mar 04, 2026 6:30 PM"
  // Return format: "2026-03-04"
  try {
    const date = new Date(dateTimeString);
    if (isNaN(date.getTime())) {
      console.warn('Invalid date string:', dateTimeString);
      return '1970-01-01';
    }
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  } catch (err) {
    console.error('Error parsing date:', dateTimeString, err);
    return '1970-01-01';
  }
};

export const getDateParts = (
  dateString: string
): { year: number; month: number; day: number } => {
  const [yearStr, monthStr, dayStr] = dateString.split('-');
  return {
    year: parseInt(yearStr, 10),
    month: parseInt(monthStr, 10),
    day: parseInt(dayStr, 10),
  };
};
