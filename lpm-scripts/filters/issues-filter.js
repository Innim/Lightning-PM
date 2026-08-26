/**
 * Фильтр списка задач по тегам и людям.
 *
 * Человек выбирается вместе с ролью: в мультиселекте он есть в группе
 * «Исполнители» и, если реально тестировал задачи проекта, в группе
 * «Тестировщики». Поэтому роли всюду ходят разными списками идентификаторов.
 */
lpm.components.issueListFilter = {
    /**
     * Проверяет, подходит ли задача под выбранных людей: достаточно совпадения
     * хотя бы по одному исполнителю или хотя бы по одному тестировщику.
     *
     * Исполнители берутся из вложенных элементов с `data-member-id`,
     * тестировщики - из атрибута `data-tester-ids` самого элемента задачи.
     *
     * @param {Element} el Строка списка задач или стикер доски.
     * @param {Array<number>} memberIds Выбранные исполнители.
     * @param {Array<number>} testerIds Выбранные тестировщики.
     * @returns {boolean}
     */
    matchesUsers: function (el, memberIds, testerIds) {
        if (memberIds.length === 0 && testerIds.length === 0) {
            return true;
        }

        if (memberIds.length > 0) {
            const issueMemberIds = [...el.querySelectorAll('[data-member-id]')]
                .map((link) => parseInt(link.getAttribute('data-member-id')));
            if (memberIds.some((userId) => issueMemberIds.includes(userId))) {
                return true;
            }
        }

        if (testerIds.length > 0) {
            const issueTesterIds = (el.getAttribute('data-tester-ids') || '')
                .split(',')
                .filter((userId) => userId !== '')
                .map((userId) => parseInt(userId));
            if (testerIds.some((userId) => issueTesterIds.includes(userId))) {
                return true;
            }
        }

        return false;
    },

    /**
     * Создаёт компонент фильтра.
     *
     * @param {string} selector Селектор корневого элемента фильтра.
     * @param {function(): Iterable<Element>} getIssueElements Элементы задач, которые фильтруются.
     * @param {function(Element, Array<string>, Array<number>, Array<number>): boolean} filter
     *        Предикат показа: (элемент, теги, id исполнителей, id тестировщиков).
     */
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
                    userRoleIcon(user) {
                        return user.role === 'tester' ? 'fa-flask' : 'fa-wrench';
                    },

                    selectedIdsByRole(role) {
                        return this.selectedUsers
                            .filter((user) => user.role === role)
                            .map((user) => user.userId);
                    },

                    selectUsers(memberIds, testerIds = []) {
                        const groups = this.$refs.userMultiselect.options;
                        const options = groups.reduce((all, group) => all.concat(group.users), []);
                        this.selectedUsers = options.filter((user) =>
                            (user.role === 'tester' ? testerIds : memberIds).includes(user.userId));
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
                        const memberIds = this.selectedIdsByRole('member');
                        const testerIds = this.selectedIdsByRole('tester');

                        this.getIssueElements().forEach((el) => {
                            this.showElement(el, filter(el, selectedTags, memberIds, testerIds));
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