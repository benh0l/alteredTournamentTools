import { startStimulusApp } from '@symfony/stimulus-bundle';

// Start Stimulus application with auto-discovery
const app = startStimulusApp();

// Enable debug mode for development
// Shows controller connect/disconnect in console
app.debug = true;

// Export for global access (useful for debugging)
window.Stimulus = app;

// Register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
