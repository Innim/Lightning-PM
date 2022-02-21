/**
 * Компонент фильтра задач на Scrum-доске.
 */
document.addEventListener('DOMContentLoaded', () => {
    (function initScrumBoardFilter(filterElementSelector, onChange) {
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
                getStickerTitles(selector = '.sticker-issue-title') {
                    return document.querySelectorAll(selector);
                },
                getStickerElement(el) {
                    return el?.parentElement?.parentElement;
                },
                showElement(el, show) {
                    el.style.display = show ? 'block' : 'none';
                },
                filterStickers(selectedTags) {
                    this.getStickerTitles().forEach((el) => {
                        const stickerTitle = el.innerText;
                        const lastTagIndex = stickerTitle.lastIndexOf(']');
                        const stickerTags = stickerTitle.substr(0, lastTagIndex + 1);
                        const hasTag = selectedTags.some((tag) => stickerTags.includes(tag));
                        this.showElement(this.getStickerElement(el), hasTag);
                    });
                },
                showAllStickers() {
                    this.getStickerTitles().forEach((el) => {
                        this.showElement(this.getStickerElement(el), true);
                    });
                }
            }
        });
    })('#scrumBoardFilter', issuePage.scrumColUpdateInfo);
});
