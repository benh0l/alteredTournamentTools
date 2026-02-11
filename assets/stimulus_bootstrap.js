import { startStimulusApp } from '@symfony/stimulus-bundle';
import ContactModalController from './controllers/contact_modal_controller.js';
import ShareImageController from './controllers/share_image_controller.js';
import PlayerSearchController from './controllers/player_search_controller.js';

// Start Stimulus application with auto-discovery
const app = startStimulusApp();

// Enable debug mode for development
// Shows controller connect/disconnect in console
app.debug = true;

// Export for global access (useful for debugging)
window.Stimulus = app;

// Register any custom, 3rd party controllers here
app.register('contact-modal', ContactModalController);
app.register('share-image', ShareImageController);
app.register('player-search', PlayerSearchController);
