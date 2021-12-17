import {filterByTag} from './filter-by-tag';

// Инициализация компонента фильтров по тегу на Scrum доске
filterByTag('#scrumBoardFilters', function() {
    issuePage.scumColUpdateInfo();
});
