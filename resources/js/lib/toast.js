import { reactive } from 'vue';

export const toastState = reactive({
    items: [],
});

let nextId = 0;

export const toast = {
    add({ title, message, type = 'success', duration = 5000 }) {
        const id = nextId++;
        const item = { id, title, message, type };
        
        toastState.items.push(item);

        if (duration > 0) {
            setTimeout(() => {
                this.remove(id);
            }, duration);
        }

        return id;
    },

    success(message, title = 'Success') {
        return this.add({ title, message, type: 'success' });
    },

    error(message, title = 'Error') {
        return this.add({ title, message, type: 'error' });
    },

    remove(id) {
        const index = toastState.items.findIndex(i => i.id === id);
        if (index !== -1) {
            toastState.items.splice(index, 1);
        }
    }
};
