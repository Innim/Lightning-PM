lpm.components.issueListFilter = {
    init: function ({selector = '#issueListFilter', getIssueElements, filter}) {
        return (function issueListFilter(filterElementSelector, onChange) {
            return new Vue({
                el: filterElementSelector,
                data: {
                    selectedTags: [],
                    selectedUsers: [],
                    options: []
                },
                computed: {
                    hasActiveFilters() {
                        return this.selectedTags.length > 0 || this.selectedUsers.length > 0;
                    }
                },
                watch: {
                    selectedTags: {
                        handler: function () {
                            this.applyFilters();
                        },
                        deep: true
                    },
                    selectedUsers: {
                        handler: function () {
                            this.applyFilters();
                        },
                        deep: true
                    }
                },
                methods: {
                    selectUsers(userIds) {
                        const multiselect = this.$refs.userMultiselect;
                        const usersToSelect = multiselect.options.filter(user => userIds.includes(user.userId));
                        if (usersToSelect) {
                            this.selectedUsers = usersToSelect;
                        }
                    },

                    getIssueElements() {
                        const rows = getIssueElements();
                        return [...rows];
                    },

                    showElement(el, show) {
                        el.hidden = !show;
                    },

                    applyFilters() {
                        const hasTagFilter = this.selectedTags.length > 0;
                        const hasUserFilter = this.selectedUsers.length > 0;

                        if (!hasTagFilter && !hasUserFilter) {
                            this.showAllIssues();
                            onChange({ tags: this.selectedTags, users: this.selectedUsers });
                            return;
                        }
                        
                        const selectedTags = this.selectedTags;
                        const selectedUserIds = this.selectedUsers.map(user => user.userId);

                        this.getIssueElements().forEach((el) => {
                            this.showElement(el, filter(el, selectedTags, selectedUserIds));
                        });

                        onChange({ tags: this.selectedTags, users: this.selectedUsers });
                    },

                    showAllIssues() {
                        this.getIssueElements().forEach((el) => this.showElement(el, true));
                    },

                    clearAllFilters() {
                        this.selectedTags = [];
                        this.selectedUsers = [];
                    }
                }
            });
        })(selector, issuePage.onFilterChanged);
    }
};