import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["backdrop"]
    static values = { storageKey: String }

    connect() {
        if (!localStorage.getItem(this.storageKeyValue)) {
            this.backdropTarget.classList.remove("hidden")
        }
    }

    dismiss() {
        localStorage.setItem(this.storageKeyValue, "1")
        this.backdropTarget.classList.add("hidden")
    }
}
