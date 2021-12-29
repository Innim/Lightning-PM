/**
 * Компонент фильтра по тегам в списке задач.
 */
export function issueListFilter(filterElementSelector, onChange) {
    return new Vue({
        el: filterElementSelector,
        data: {
            selectedTags: null,
            options: []
        },
        watch: {
            selectedTags: function(selectedTags) {
                if (selectedTags.length) {
                    this.filterStickers(selectedTags);
                } else {
                    this.showAllStickers();
                }
                onChange(selectedTags);
            }
        },
        methods: {
            getRows(selector ) {
                const issuesList = document.getElementById('issuesList');
                const rows = issuesList.tBodies[0].children;
                return document.querySelectorAll(selector);
            },
            getStickerElement(el) {
                // return el?.parentElement?.parentElement;
            },
            showElement(el, show) {
                // el.style.display = show ? 'block' : 'none';
            },
            filterStickers(selectedTags) {
                this.getRows().forEach((el) => {

                });
            },
            // showAllStickers() {
            //     this.getRows().forEach((el) => {
            //         this.showElement(this.getStickerElement(el), true);
            //     });
            // }
        }
    });
}
