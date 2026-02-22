
import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { EventListView } from './components/EventListView';
import { SingleEventPage } from './components/SingleEventPage';
import { MembershipListView } from './components/MembershipView';
import { CoursesView } from './components/CoursesView';
import { UserDashboardView } from './components/UserDashboardView';

const App: React.FC = () => {
  return (
    <BrowserRouter>
      <div id="seamless-plugin-root" className="seamless-page-wrapper">
        <Routes>
          <Route path="/" element={<EventListView />} />
          <Route path="/events" element={<EventListView />} />
          <Route path="/events/:slug" element={<SingleEventPage />} />
          <Route path="/group-event/:slug" element={<SingleEventPage />} />
          <Route path="/memberships" element={<MembershipListView />} />
          <Route path="/courses" element={<CoursesView />} />
          <Route path="/dashboard" element={<UserDashboardView />} />
          {/* Fallback for unknown routes */}
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </div>
    </BrowserRouter>
  );
};

export default App;