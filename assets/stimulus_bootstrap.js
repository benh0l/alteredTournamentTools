import { startStimulusApp } from '@symfony/stimulus-bundle';
import ContactModalController from './controllers/contact_modal_controller.js';
import ShareImageController from './controllers/share_image_controller.js';
import PlayerSearchController from './controllers/player_search_controller.js';
import PairingEditorController from './controllers/pairing_editor_controller.js';
import ToastController from './controllers/toast_controller.js';
import ButtonLoaderController from './controllers/button_loader_controller.js';
import DropdownController from './controllers/dropdown_controller.js';
import ThemeSelectorController from './controllers/theme_selector_controller.js';
import OnboardingController from './controllers/onboarding_controller.js';
import RecurrenceController from './controllers/recurrence_controller.js';

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
app.register('pairing-editor', PairingEditorController);
app.register('toast', ToastController);
app.register('button-loader', ButtonLoaderController);
app.register('dropdown', DropdownController);
app.register('theme-selector', ThemeSelectorController);
app.register('onboarding', OnboardingController);
app.register('recurrence', RecurrenceController);
