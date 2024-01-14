ms3.order = {
    init () {

    },
    async add (formData) {
        formData.append('ms3_action', 'order/add')
        const response = await ms3.request.send(formData)
    },
    async remove (formData) {
        formData.append('ms3_action', 'order/remove')
        const response = await ms3.request.send(formData)
    },
}