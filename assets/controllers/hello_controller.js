import { Controller } from '@hotwired/stimulus';

/**
 * Test controller to verify Stimulus is working.
 *
 * Usage:
 * <div data-controller="hello" data-hello-name-value="World">
 *     <input data-hello-target="input" type="text">
 *     <button data-action="click->hello#greet">Greet</button>
 *     <span data-hello-target="output"></span>
 * </div>
 *
 * @see https://stimulus.hotwired.dev/reference/controllers
 */
export default class extends Controller {
    // Define targets - elements with data-hello-target="name"
    static targets = ['input', 'output'];

    // Define values - typed data attributes
    static values = {
        name: { type: String, default: 'World' },
        count: { type: Number, default: 0 }
    };

    /**
     * Called when controller connects to DOM.
     * CRITICAL: Verify this appears in browser console.
     */
    connect() {
        console.log('Hello controller connected!');
        console.log('Name value:', this.nameValue);

        // Initial greeting
        if (this.hasOutputTarget) {
            this.outputTarget.textContent = `Hello, ${this.nameValue}!`;
        }
    }

    /**
     * Called when controller disconnects from DOM.
     * CRITICAL: Always clean up (from project-context.md).
     */
    disconnect() {
        console.log('Hello controller disconnected.');
    }

    /**
     * Action: Greet with input value or default name.
     * Triggered by: data-action="click->hello#greet"
     */
    greet() {
        const name = this.hasInputTarget && this.inputTarget.value
            ? this.inputTarget.value
            : this.nameValue;

        this.countValue++;

        if (this.hasOutputTarget) {
            this.outputTarget.textContent = `Hello, ${name}! (Greeted ${this.countValue} times)`;
        }

        console.log(`Greeted: ${name}`);
    }

    /**
     * Called when nameValue changes.
     */
    nameValueChanged() {
        console.log('Name value changed to:', this.nameValue);
    }

    /**
     * Called when countValue changes.
     */
    countValueChanged() {
        console.log('Count value changed to:', this.countValue);
    }
}
