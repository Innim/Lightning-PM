/**
 * Компонент фильтра задач на Scrum-доске.
 */
document.addEventListener('DOMContentLoaded', () => {
    issuePage.filterVm = lpm.components.issueListFilter.init({
        selector: '#scrumBoardFilter',
        filter: function (el, tags, userIds) {
            if (tags.length > 0) {

                const titleEl = el.querySelector('.sticker-issue-title');

                const stickerTitle = titleEl.innerText;
                const lastTagIndex = stickerTitle.lastIndexOf(']');
                const stickerTags = stickerTitle.substr(0, lastTagIndex + 1);
                if (stickerTags.length == 0) return false;

                const hasTag = tags.some((tag) => stickerTags.includes(tag));
                if (!hasTag) return false;
            }

            if (userIds.length > 0) {
                const memberListEl = el.querySelector('.sticker-issue-members');
                if (!memberListEl) return false;

                const memberLinks = memberListEl.querySelectorAll('[data-member-id]');
                const memberIds = Array.from(memberLinks).map(link =>
                    parseInt(link.getAttribute('data-member-id'))
                );
                const hasMatchingUser = userIds.some((userId) => memberIds.includes(userId));
                if (!hasMatchingUser) return false;
            }

            return true;
        },
        getIssueElements: function () {
            return document.querySelectorAll('.scrum-board-sticker');
        },
    });
});
