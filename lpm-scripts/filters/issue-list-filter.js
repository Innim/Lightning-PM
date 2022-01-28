/**
 * Компонент фильтра по тегам в списке задач.
 */
document.addEventListener('DOMContentLoaded', () => {
    (function issueListFilter(filterElementSelector, onChange) {
        return new Vue({
            el: filterElementSelector,
            data: {
                selectedTags: null,
                options: []
            },
            watch: {
                selectedTags: function(selectedTags) {
                    if (selectedTags.length) {
                        this.filterIssues(selectedTags);
                    } else {
                        this.showAllIssues();
                    }
                    onChange(selectedTags);
                }
            },
            methods: {
                getRows(id = 'issuesList') {
                    const issuesList = document.getElementById(id);
                    const rows = issuesList.tBodies[0]?.children;
                    return [...rows];
                },
                getIssueColElement({children}) {
                    return children[2].querySelector('.issue-name > .issue-name');
                },
                showElement(el, show) {
                    el.hidden = !show;
                },
                filterIssues(selectedTags) {
                    this.getRows().forEach((row) => {
                        const issueColElement = this.getIssueColElement(row);
                        const issueTitle = issueColElement.innerText;
                        const lastTagIndex = issueTitle.lastIndexOf(']');
                        const issueTags = issueTitle.substr(0, lastTagIndex + 1);
                        const hasTag = selectedTags.some((tag) => issueTags.includes(tag));
                        this.showElement(row, hasTag);
                    });
                },
                showAllIssues() {
                    this.getRows().forEach((row) => this.showElement(row, true));
                }
            }
        });
    })('#issueListFilter', issuePage.scrumColUpdateInfo);
});
