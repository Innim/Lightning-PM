export function initFilters() {
    Vue.component('vue-multiselect', window.VueMultiselect.default);
    const filters = new Vue({
        el: '#scrumBoardFilters',
        data: {
            message: 'Фильтры компонента',
            value: null,
            options: []
        },
    });
}
