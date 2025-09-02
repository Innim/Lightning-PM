/**
 * Компонент фильтра по тегам и пользователям в списке задач.
 */
document.addEventListener('DOMContentLoaded', () => {
    issuePage.filterVm = lpm.components.issueListFilter.init({
        filter: function (row, tags, userIds) {
            let showRow = true;

            if (tags.length > 0 && showRow) {
                const labelsStr = row.getAttribute('data-labels');
                if (labelsStr) {
                    const labels = labelsStr.split(',');
                    const hasMatchingTag = tags.some((tag) => labels.includes(tag));
                    showRow = hasMatchingTag;
                } else {
                    showRow = false;
                }
            }

            if (userIds.length > 0 && showRow) {
                const memberListDiv = row.querySelector('.member-list');
                if (memberListDiv) {
                    const memberLinks = memberListDiv.querySelectorAll('[data-member-id]');
                    const memberIds = Array.from(memberLinks).map(link =>
                        parseInt(link.getAttribute('data-member-id'))
                    );
                    const hasMatchingUser = userIds.some((userId) => memberIds.includes(userId));
                    showRow = hasMatchingUser;
                } else {
                    showRow = false;
                }
            }

            return showRow;
        },
        getIssueElements: function () {
            const issuesList = document.getElementById('issuesList');
            return issuesList.tBodies[0]?.children;
        },
    });
});
