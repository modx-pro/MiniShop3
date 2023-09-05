ms3.form = {
    init () {
        document.addEventListener('submit', event => {
            if (event.target.classList.contains('ms3_form')) {
                event.preventDefault()
                const form = event.target
                const formData = new FormData(form)
                this.send(formData)
            }
        })
    },
    async send (formData) {
        const response = await this.request.post(formData)
        console.log(response)
    }
}