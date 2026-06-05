import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["fields"]

    connect() {
        this.toggle()
    }

    toggle() {
        const checkbox = this.element.querySelector('input[type="checkbox"]')
        const visible = checkbox ? checkbox.checked : false
        this.fieldsTargets.forEach(el => {
            el.classList.toggle("hidden", !visible)
        })
    }
}
