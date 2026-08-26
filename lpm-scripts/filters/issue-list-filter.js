/**
 * Компонент фильтра по тегам и пользователям в списке задач.
 */
document.addEventListener('DOMContentLoaded', () => {
    issuePage.filterVm = lpm.components.issueListFilter.init({
        filter: function (row, tags, memberIds, testerIds) {
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

            if (showRow) {
                showRow = lpm.components.issueListFilter.matchesUsers(row, memberIds, testerIds);
            }

            return showRow;
        },
        getIssueElements: function () {
            const issuesList = document.getElementById('issuesList');
            return issuesList.tBodies[0]?.children;
        },
    });
});
