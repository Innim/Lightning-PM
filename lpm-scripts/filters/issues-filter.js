/**
 * Фильтр списка задач по тегам и людям.
 *
 * Человек выбирается вместе с ролью: в мультиселекте он есть в группе
 * «Исполнители» и, если реально тестировал задачи проекта, в группе
 * «Тестировщики». Поэтому роли всюду ходят разными списками идентификаторов.
 *
 * Отдельным переключателем можно оставить только задачи с несколькими
 * исполнителями; он есть не во всех местах, где выводится компонент.
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
     * Проверяет, что у задачи больше одного исполнителя.
     *
     * Считаются только исполнители: тестировщики задачи перечислены в её
     * атрибуте `data-tester-ids` и меток `data-member-id` не имеют.
     *
     * @param {Element} el Строка списка задач или стикер доски.
     * @returns {boolean}
     */
    hasSeveralMembers: function (el) {
        return el.querySelectorAll('[data-member-id]').length > 1;
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
        const component = this;
        // Переключатель «несколько исполнителей» выводится не на всех страницах;
        // о его наличии шаблон сообщает атрибутом на корневом элементе
        const rootEl = document.querySelector(selector);
        const multiMemberFilterEnabled = !!rootEl && rootEl.hasAttribute('data-multi-member-filter');

        return (function issueListFilter(filterElementSelector, onChange) {
            return new Vue({
                el: filterElementSelector,
                data: {
                    selectedTags: [],
                    selectedUsers: [],
                    multiMemberOnly: false,
                    options: []
                },
                computed: {
                    hasActiveFilters() {
                        return this.selectedTags.length > 0
                            || this.selectedUsers.length > 0
                            || this.multiMemberOnly;
                    }
                },
                watch: {
                    multiMemberOnly: function () {
                        this.applyFilters();
                    },
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

                    /**
                     * Включает или выключает отбор задач с несколькими исполнителями.
                     *
                     * Там, где переключателя нет, вызов ничего не меняет: иначе
                     * ключ из чужой ссылки включил бы фильтр, который на этой
                     * странице нечем ни увидеть, ни выключить.
                     *
                     * @param {boolean} value
                     */
                    setMultiMemberOnly(value) {
                        this.multiMemberOnly = multiMemberFilterEnabled && value;
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

                    /**
                     * Текущий выбор фильтров - то, что уходит подписчику
                     * и сохраняется в адресе страницы.
                     *
                     * @returns {{tags: Array<string>, users: Array<Object>, multiMemberOnly: boolean}}
                     */
                    filterState() {
                        return {
                            tags: this.selectedTags,
                            users: this.selectedUsers,
                            multiMemberOnly: this.multiMemberOnly
                        };
                    },

                    applyFilters() {
                        const hasTagFilter = this.selectedTags.length > 0;
                        const hasUserFilter = this.selectedUsers.length > 0;
                        const multiMemberOnly = this.multiMemberOnly;

                        if (!hasTagFilter && !hasUserFilter && !multiMemberOnly) {
                            this.showAllIssues();
                            onChange(this.filterState());
                            return;
                        }
                        
                        const selectedTags = this.selectedTags;
                        const memberIds = this.selectedIdsByRole('member');
                        const testerIds = this.selectedIdsByRole('tester');

                        // Условия складываются: задача остаётся, только если проходит
                        // и по числу исполнителей, и по тегам с людьми
                        this.getIssueElements().forEach((el) => {
                            const show = (!multiMemberOnly || component.hasSeveralMembers(el))
                                && filter(el, selectedTags, memberIds, testerIds);
                            this.showElement(el, show);
                        });

                        onChange(this.filterState());
                    },

                    showAllIssues() {
                        this.getIssueElements().forEach((el) => this.showElement(el, true));
                    },

                    clearAllFilters() {
                        this.selectedTags = [];
                        this.selectedUsers = [];
                        this.multiMemberOnly = false;
                    }
                }
            });
        })(selector, issuePage.onFilterChanged);
    }
};