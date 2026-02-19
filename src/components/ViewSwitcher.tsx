import React from 'react';
import type { ViewType } from '../types/event';

interface ViewSwitcherProps {
  currentView: ViewType;
  onViewChange: (view: ViewType) => void;
}

export const ViewSwitcher: React.FC<ViewSwitcherProps> = ({ currentView, onViewChange }) => {
  return (
    <div className="seamless-view-switcher">
      {/* List View - Hidden on mobile */}
      <span
        onClick={() => onViewChange('list')}
        className={`seamless-view-switcher-button seamless-view-switcher-button-list ${currentView === 'list'
            ? 'seamless-view-switcher-button-active'
            : 'seamless-view-switcher-button-inactive'
          }`}
      >
        List View
      </span>
      {/* Grid View */}
      <span
        onClick={() => onViewChange('grid')}
        className={`seamless-view-switcher-button ${currentView === 'grid'
            ? 'seamless-view-switcher-button-active'
            : 'seamless-view-switcher-button-inactive'
          }`}
      >
        Grid View
      </span>
      {/* Calendar View */}
      <span
        onClick={() => onViewChange('calendar')}
        className={`seamless-view-switcher-button ${currentView === 'calendar'
            ? 'seamless-view-switcher-button-active'
            : 'seamless-view-switcher-button-inactive'
          }`}
      >
        Calendar View
      </span>
    </div>
  );
};
