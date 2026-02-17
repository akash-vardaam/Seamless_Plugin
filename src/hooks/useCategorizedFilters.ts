import { useMemo } from 'react';
import type { Event, Category } from '../types/event';

interface UseCategorizedFiltersReturn {
  audiences: Category[];
  focuses: Category[];
  localChapters: Category[];
  years: string[];
}

/**
 * Intelligently categorize a category based on its slug and name patterns
 * Uses dynamic pattern matching derived from common slug keywords
 */
const categorizeSingle = (category: Category): 'audience' | 'focus' | 'localChapter' | 'other' => {
  const slugLower = category.slug.toLowerCase();
  const nameLower = category.name.toLowerCase();

  // Patterns for audience categories
  const audiencePatterns = /new-to-practice|partner|practicing|resident|retired|student|attendee/i;
  // Patterns for focus/activity categories
  const focusPatterns = /advocacy|cme|career|practice|professional|development|discussion|echo|ksa|networking|social/i;
  // Patterns for local chapter/region categories
  const localChapterPatterns = /central|east|west|metro|minnesota|valley|lakes|superior|woods|region|range|southeast|southern|online|state/i;
  // Exclude system/display categories
  const systemPatterns = /display|calendar|feed|tile|foundation/i;

  // Skip system/display categories
  if (systemPatterns.test(slugLower)) {
    return 'other';
  }

  // Test against patterns
  if (audiencePatterns.test(slugLower) || audiencePatterns.test(nameLower)) {
    return 'audience';
  }

  if (focusPatterns.test(slugLower) || focusPatterns.test(nameLower)) {
    return 'focus';
  }

  if (localChapterPatterns.test(slugLower) || localChapterPatterns.test(nameLower)) {
    return 'localChapter';
  }

  return 'other';
};

/**
 * Extract and categorize filters from events dynamically based on API response
 * Categories are intelligently grouped based on slug and name patterns
 */
export const useCategorizedFilters = (events: Event[]): UseCategorizedFiltersReturn => {
  const { audiences, focuses, localChapters, years } = useMemo(() => {
    const audienceMap = new Map<string, Category>();
    const focusMap = new Map<string, Category>();
    const localChapterMap = new Map<string, Category>();
    const yearSet = new Set<string>();

    // Process each event
    events.forEach((event) => {
      // Extract year from event date
      if (event.start_date) {
        const year = new Date(event.start_date).getFullYear().toString();
        yearSet.add(year);
      }

      // Dynamically categorize categories based on slug and name patterns
      if (event.categories && Array.isArray(event.categories)) {
        event.categories.forEach((category) => {
          const categoryType = categorizeSingle(category);

          switch (categoryType) {
            case 'audience':
              if (!audienceMap.has(category.id)) {
                audienceMap.set(category.id, category);
              }
              break;
            case 'focus':
              if (!focusMap.has(category.id)) {
                focusMap.set(category.id, category);
              }
              break;
            case 'localChapter':
              if (!localChapterMap.has(category.id)) {
                localChapterMap.set(category.id, category);
              }
              break;
            default:
              // Skip 'other' categories
              break;
          }
        });
      }
    });

    return {
      audiences: Array.from(audienceMap.values()),
      focuses: Array.from(focusMap.values()),
      localChapters: Array.from(localChapterMap.values()),
      years: Array.from(yearSet).sort((a, b) => parseInt(b) - parseInt(a)),
    };
  }, [events]);

  return {
    audiences,
    focuses,
    localChapters,
    years,
  };
};
