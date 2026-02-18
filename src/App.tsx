
import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { EventListView } from './components/EventListView';
import { SingleEventPage } from './components/SingleEventPage';

const App: React.FC = () => {
  return (
    <BrowserRouter>
      <div id="seamless-event-container" className="seamless-page-wrapper">
        <Routes>
          <Route path="/" element={<EventListView />} />
          <Route path="/events" element={<EventListView />} />
          <Route path="/event/:slug" element={<SingleEventPage />} />
          {/* Fallback for unknown routes */}
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </div>
    </BrowserRouter>
  );
};

export default App;