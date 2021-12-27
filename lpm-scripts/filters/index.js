import {initScrumBoardFilter} from './scrum-board-filter';
import {issueListFilter} from './issue-list-filter';

// Подключаем компонент vue-multiselect
Vue.component('vue-multiselect', window.VueMultiselect.default);

initScrumBoardFilter('#scrumBoardFilter', issuePage.scrumColUpdateInfo);

// Инициализация компонента фильтров по тегу в списке задач
// issueListFilter('#issueListFilter', () => {
//     issuePage.scrumColUpdateInfo();
// });
