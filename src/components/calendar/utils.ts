export const getCategoryColor = (
  event: any
): { bg: string; text: string; border: string } => {
  // Extract color from categories if available
  if (event.categories && event.categories.length > 0) {
    const category = event.categories[0];
    const slug = category.slug?.toLowerCase() || '';

    // Color mapping based on category slugs
    if (slug.includes('advocacy'))
      return { bg: 'bg-orange-100', text: 'text-orange-700', border: '#f97316' };
    if (slug.includes('cme'))
      return { bg: 'bg-pink-100', text: 'text-pink-700', border: '#ec4899' };
    if (slug.includes('conference'))
      return { bg: 'bg-blue-100', text: 'text-blue-700', border: '#3b82f6' };
    if (slug.includes('summer'))
      return { bg: 'bg-green-100', text: 'text-green-700', border: '#22c55e' };
    if (slug.includes('ksa'))
      return { bg: 'bg-purple-100', text: 'text-purple-700', border: '#a855f7' };
    if (slug.includes('region'))
      return { bg: 'bg-pink-100', text: 'text-pink-700', border: '#ec4899' };
  }
  return { bg: 'bg-teal-100', text: 'text-teal-700', border: '#14b8a6' };
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
