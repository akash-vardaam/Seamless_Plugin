import React from 'react';
import { SearchInput } from './SearchInput';
import { CustomDropdown } from './CustomDropdown';
import type { Category } from '../types/event';

interface FilterBarProps {
  search: string;
  onSearchChange: (value: string) => void;
  status: string;
  onStatusChange: (value: string) => void;
  audience: string;
  onAudienceChange: (value: string) => void;
  audiences: Category[];
  focus: string;
  onFocusChange: (value: string) => void;
  focuses: Category[];
  localChapter: string;
  onLocalChapterChange: (value: string) => void;
  localChapters: Category[];
  year: string;
  onYearChange: (value: string) => void;
  years: string[];
  onReset: () => void;
}

export const FilterBar: React.FC<FilterBarProps> = ({
  search,
  onSearchChange,
  status,
  onStatusChange,
  audience,
  onAudienceChange,
  audiences,
  focus,
  onFocusChange,
  focuses,
  localChapter,
  onLocalChapterChange,
  localChapters,
  year,
  onYearChange,
  years,
  onReset,
}) => {
  return (
    <>
      <div className="seamless-filter-bar">
        <div className="seamless-filter-bar-content">
          <div className="seamless-filter-bar-inner">
            {/* Desktop Layout */}
            <div className="seamless-filter-bar-desktop">
              {/* Search Header and Input - Row 1 */}
              <div className="seamless-filter-header-row">
                <h3 className="seamless-filter-label" style={{ fontFamily: 'Montserrat' }}>
                  SEARCH AND FILTER
                </h3>
                <div className="seamless-filter-search-wrapper">
                  <SearchInput value={search} onChange={onSearchChange} placeholder="Search event by name" />
                </div>
              </div>

              {/* Filters Row - Row 2 */}
              <div className="seamless-filter-controls-row">
                {/* Status Dropdown */}
                <div className="seamless-filter-control">
                  <CustomDropdown
                    value={status}
                    onChange={onStatusChange}
                    options={[
                      { value: 'upcoming', label: 'UPCOMING' },
                      { value: 'current', label: 'CURRENT' },
                      { value: 'past', label: 'PAST' }
                    ]}
                    placeholder="UPCOMING"
                  />
                </div>

                {/* Audience Dropdown */}
                <div className="seamless-filter-control">
                  <CustomDropdown
                    value={audience}
                    onChange={onAudienceChange}
                    options={[
                      { value: '', label: 'AUDIENCE' },
                      ...audiences.map(aud => ({ value: aud.id, label: aud.name.toUpperCase() }))
                    ]}
                    placeholder="AUDIENCE"
                  />
                </div>

                {/* Focus Dropdown */}
                <div className="seamless-filter-control">
                  <CustomDropdown
                    value={focus}
                    onChange={onFocusChange}
                    options={[
                      { value: '', label: 'FOCUS' },
                      ...focuses.map(foc => ({ value: foc.id, label: foc.name.toUpperCase() }))
                    ]}
                    placeholder="FOCUS"
                  />
                </div>

                {/* Local Chapters Dropdown */}
                <div className="seamless-filter-control">
                  <CustomDropdown
                    value={localChapter}
                    onChange={onLocalChapterChange}
                    options={[
                      { value: '', label: 'LOCAL CHAPTERS / REGIONS' },
                      ...localChapters.map(lc => ({ value: lc.id, label: lc.name.toUpperCase() }))
                    ]}
                    placeholder="LOCAL CHAPTERS / REGIONS"
                  />
                </div>

                {/* Year Dropdown */}
                <div className="seamless-filter-control">
                  <CustomDropdown
                    value={year}
                    onChange={onYearChange}
                    options={[
                      { value: '', label: 'YEAR' },
                      ...years.map(yr => ({ value: yr, label: yr }))
                    ]}
                    placeholder="YEAR"
                  />
                </div>

                {/* Reset Button */}
                <div className="seamless-filter-control">
                  <div
                    onClick={onReset}
                    className="seamless-button seamless-button-primary seamless-button-full"
                    style={{ fontFamily: 'Montserrat' }}
                    role="button"
                    tabIndex={0}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' || e.key === ' ') {
                        onReset();
                      }
                    }}
                  >
                    RESET
                  </div>
                </div>
              </div>
            </div>

            {/* Mobile Layout */}
            <div className="seamless-filter-bar-mobile">
              {/* Title */}
              <h3 className="seamless-filter-label seamless-w-full" style={{ fontFamily: 'Montserrat' }}>
                SEARCH AND FILTER
              </h3>

              {/* Search Bar - Full Width */}
              <div className="seamless-filter-mobile-control">
                <SearchInput value={search} onChange={onSearchChange} placeholder="Search event by name" />
              </div>

              {/* Filter Dropdowns - Stacked */}
              <div className="seamless-filter-mobile-controls">
                {/* Status Dropdown */}
                <select
                  value={status}
                  onChange={(e) => onStatusChange(e.target.value)}
                  className="seamless-select-dropdown seamless-filter-mobile-control"
                  style={{ fontFamily: 'Montserrat' }}
                >
                  <option value="upcoming">UPCOMING</option>
                  <option value="current">CURRENT</option>
                  <option value="past">PAST</option>
                </select>

                {/* Audience Dropdown */}
                <select
                  value={audience}
                  onChange={(e) => onAudienceChange(e.target.value)}
                  className="seamless-select-dropdown seamless-filter-mobile-control"
                  style={{ fontFamily: 'Montserrat' }}
                >
                  <option value="">AUDIENCE</option>
                  {audiences.map((aud) => (
                    <option key={aud.id} value={aud.id}>
                      {aud.name.toUpperCase()}
                    </option>
                  ))}
                </select>

                {/* Focus Dropdown */}
                <select
                  value={focus}
                  onChange={(e) => onFocusChange(e.target.value)}
                  className="seamless-select-dropdown seamless-filter-mobile-control"
                  style={{ fontFamily: 'Montserrat' }}
                >
                  <option value="">FOCUS</option>
                  {focuses.map((foc) => (
                    <option key={foc.id} value={foc.id}>
                      {foc.name.toUpperCase()}
                    </option>
                  ))}
                </select>

                {/* Local Chapters Dropdown */}
                <select
                  value={localChapter}
                  onChange={(e) => onLocalChapterChange(e.target.value)}
                  className="seamless-select-dropdown seamless-filter-mobile-control"
                  style={{ fontFamily: 'Montserrat' }}
                >
                  <option value="">LOCAL CHAPTERS / REGIONS</option>
                  {localChapters.map((lc) => (
                    <option key={lc.id} value={lc.id}>
                      {lc.name.toUpperCase()}
                    </option>
                  ))}
                </select>

                {/* Year Dropdown */}
                <select
                  value={year}
                  onChange={(e) => onYearChange(e.target.value)}
                  className="seamless-select-dropdown seamless-filter-mobile-control"
                  style={{ fontFamily: 'Montserrat' }}
                >
                  <option value="">YEAR</option>
                  {years.map((yr) => (
                    <option key={yr} value={yr}>
                      {yr}
                    </option>
                  ))}
                </select>

                {/* Reset Button */}
                <button
                  onClick={onReset}
                  className="seamless-button seamless-button-primary seamless-button-full seamless-filter-mobile-control"
                  style={{ fontFamily: 'Montserrat' }}
                >
                  RESET
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
};
