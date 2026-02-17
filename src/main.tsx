import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './styles/index.css'
import App from './App.tsx'

// Check for WordPress shortcode container first, fall back to dev container
const containerElement = document.getElementById('seamless-react-root') || document.getElementById('events-react-root') || document.getElementById('root');

if (containerElement) {
  // Add seamless-event-container ID for CSS scoping
  containerElement.id = 'seamless-event-container';

  createRoot(containerElement).render(
    <StrictMode>
      <App />
    </StrictMode>,
  );
} else {
  // Log warning if no container found (helpful for debugging)
  console.warn(
    '[Events React] No mount point found. Expected #seamless-react-root (WordPress), #events-react-root (WordPress), or #root (development).'
  );
}
