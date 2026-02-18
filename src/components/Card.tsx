import React from 'react';
import { Link } from 'react-router-dom';
import type { Event } from '../types/event';


interface CardProps {
  item: Event;
  layout?: 'list' | 'grid';
}

const formatDateRange = (startDate: string, endDate: string): string => {
  try {
    const start = new Date(startDate);
    const end = new Date(endDate);

    // If same date, just return formatted date once
    if (start.toDateString() === end.toDateString()) {
      return start.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
    }

    // Different dates - show range
    const startDay = start.toLocaleDateString('en-US', { weekday: 'long' });
    const endDay = end.toLocaleDateString('en-US', { weekday: 'long' });
    const startDateStr = start.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });
    const endDateStr = end.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

    return `${startDay} - ${endDay}, ${startDateStr} - ${endDateStr}`;
  } catch {
    return startDate;
  }
};

const formatTimeRange = (startDate: string, endDate: string): string => {
  try {
    const start = new Date(startDate);
    const end = new Date(endDate);
    const startTime = start.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    const endTime = end.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    const timezone = 'CDT';
    return `${startTime} – ${endTime} ${timezone}`;
  } catch {
    return '';
  }
};

const createItemSlug = (title: string, id: string): string => {
  return title
    .toLowerCase()
    .replace(/[^\w\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-')
    .trim() || id;
};

const getItemLink = (item: Event): string => {
  // Use API slug if available, otherwise create one
  if (item.slug) {
    // If in development/standalone mode, return relative path for router
    // In production/WP, we might want full URL, but here we prioritize app navigation
    return `/event/${item.slug}`;
  }
  const slug = createItemSlug(item.title, item.id);
  return `/event/${slug}`;
};

const stripHtmlTags = (html: string): string => {
  if (!html) return '';
  return html.replace(/<[^>]*>/g, '').trim();
};

export const Card: React.FC<CardProps> = ({ item, layout = 'list' }) => {
  if (layout === 'grid') {
    return (
      <div className="seamless-card">
        {/* Image Container */}
        <div className="seamless-card-image-container">
          {item.featured_image && (
            <img
              src={item.featured_image}
              alt={item.title}
              className="seamless-card-image"
            />
          )}
        </div>

        {/* Item Details */}
        <div className="seamless-card-content">
          {/* Title */}
          <Link
            to={getItemLink(item)}
            className="seamless-card-title"
            style={{ fontFamily: 'Merriweather' }}
          >
            {item.title}
          </Link>

          {/* Date Range */}
          <p className="seamless-card-date">
            {formatDateRange(item.start_date, item.end_date || item.start_date)}
          </p>

          {/* Time */}
          <p className="seamless-card-time">
            {formatTimeRange(item.start_date, item.end_date || item.start_date)}
          </p>

          {/* SEE DETAILS Button */}
          <Link
            to={getItemLink(item)}
            className="seamless-card-see-details"
          >
            SEE DETAILS
          </Link>
        </div>
      </div>
    );
  }

  // List layout
  return (
    <div className="seamless-card-list">
      <div className="seamless-card-list-content">
        {/* Image Container */}
        <div className="seamless-card-list-image">
          {item.featured_image ? (
            <div className="seamless-card-list-image-wrapper">
              <img
                src={item.featured_image}
                alt={item.title}
                className="seamless-card-list-image-img"
              />
            </div>
          ) : (
            <div className="seamless-card-list-image-wrapper">
              <span className="seamless-card-list-no-image">No image</span>
            </div>
          )}
        </div>

        {/* Item Details */}
        <div className="seamless-card-list-details">
          {/* Title */}
          <Link
            to={getItemLink(item)}
            className="seamless-card-list-title"
            style={{ fontFamily: 'Merriweather' }}
          >
            {item.title}
          </Link>

          {/* Item Meta Information */}
          <div className="seamless-card-list-meta">
            {/* Date Range */}
            <p className="seamless-card-list-meta-item seamless-card-list-meta-date">
              {formatDateRange(item.start_date, item.end_date || item.start_date)}
            </p>

            {/* Time */}
            <p className="seamless-card-list-meta-item">
              {formatTimeRange(item.start_date, item.end_date || item.start_date)}
            </p>

            {/* Location */}
            {item.venue ? (
              <p className="seamless-card-list-meta-item">
                {item.venue.name || 'Online'}
              </p>
            ) : (
              <p className="seamless-card-list-meta-item">
                Online
              </p>
            )}
          </div>

          {/* Description */}
          {item.description && (
            <p className="seamless-card-list-description">
              {stripHtmlTags(item.description)}
            </p>
          )}

          {/* SEE DETAILS Link */}
          <Link
            to={getItemLink(item)}
            className="seamless-card-list-see-details"
          >
            SEE DETAILS
          </Link>
        </div>
      </div>
    </div>
  );
};
