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
                showElement(el, show) {
                    el.hidden = !show;
                },
                filterIssues(selectedTags) {
                    this.getRows().forEach((row) => {
                        var hasTag = false
                        const labelsStr = row.getAttribute('data-labels');
                        if (labelsStr) {
                            const labels = labelsStr.split(',');
                            hasTag = selectedTags.some((tag) => labels.includes(tag));
                        }
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
