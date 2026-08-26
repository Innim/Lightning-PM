/**
 * Компонент фильтра задач на Scrum-доске.
 */
document.addEventListener('DOMContentLoaded', () => {
    issuePage.filterVm = lpm.components.issueListFilter.init({
        selector: '#scrumBoardFilter',
        filter: function (el, tags, memberIds, testerIds) {
            if (tags.length > 0) {

                const titleEl = el.querySelector('.sticker-issue-title');

                const stickerTitle = titleEl.innerText;
                const lastTagIndex = stickerTitle.lastIndexOf(']');
                const stickerTags = stickerTitle.substr(0, lastTagIndex + 1);
                if (stickerTags.length == 0) return false;

                const hasTag = tags.some((tag) => stickerTags.includes(tag));
                if (!hasTag) return false;
            }

            return lpm.components.issueListFilter.matchesUsers(el, memberIds, testerIds);
        },
        getIssueElements: function () {
            return document.querySelectorAll('.scrum-board-sticker');
        },
    });
});
