<?php
require_once(__DIR__ . '/../init.inc.php');

/**
 * Сервис для работы с ИИ-возможностями приложения.
 */
class AiService extends LPMBaseService
{
    /**
     * Возвращает сводку обсуждения задачи, составляя её при необходимости.
     *
     * Требует прав на чтение проекта, которому принадлежит задача.
     *
     * Сводка составляется только по явному запросу и хранится одна на задачу:
     * если сохранённая сводка соответствует текущему состоянию задачи,
     * обращения к модели не будет. Ошибка генерации не сохраняется.
     *
     * @param int $issueId Идентификатор задачи.
     * @return [
     *    html: string - разметка блока сводки для страницы задачи
     * ]
     */
    public function issueSummary($issueId)
    {
        $issueId = (float)$issueId;

        try {
            $issue = Issue::load($issueId);
            if (empty($issue)) {
                return $this->error('Нет такой задачи');
            }

            // Задача запрашивается по глобальному идентификатору, поэтому права
            // на проект надо проверить здесь — так же, как это делает страница
            // задачи. Иначе текст чужой задачи ушёл бы в модель.
            $this->getProjectRequireReadPermission($issue->projectId);

            $comments = Comment::getListByInstance(LPMInstanceTypes::ISSUE, $issue->id);
            if (!IssueSummaryBuilder::isAvailableFor($issue, $comments, $this->getUserId())) {
                return $this->error('Сводка для этой задачи недоступна');
            }

            $sourceHash = IssueSummaryBuilder::sourceHash($issue, $comments);
            $summary = IssueSummary::loadByIssue($issue->getID());

            if (empty($summary) || !$summary->isActualFor($sourceHash)) {
                $result = IssueSummaryBuilder::generate($issue, $comments);
                $summary = IssueSummary::save(
                    $issue->getID(),
                    $sourceHash,
                    $result['summary'],
                    $result['model'],
                    $result['usage']
                );
            }

            $html = $this->getHtml(function () use ($issue, $summary, $sourceHash, $comments) {
                PagePrinter::aiIssueSummary($issue, $summary, $sourceHash, $comments);
            });

            $this->add2Answer('html', $html);
        } catch (AiException $e) {
            LPMLog::exception($e, LPMLog::CH_AI, ['issueId' => $issueId]);
            return $this->error($e->getLocalizedMessage());
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    /**
     * Составляет черновик чек-листа тестирования задачи.
     *
     * Требует прав на чтение проекта, которому принадлежит задача.
     *
     * Черновик не сохраняется: он возвращается для правки пользователем и
     * становится комментарием задачи только после явной публикации.
     *
     * @param int $issueId Идентификатор задачи.
     * @return [
     *    text: string - текст черновика в разметке Markdown,
     *    published: bool - публиковался ли в задаче чек-лист раньше
     * ]
     */
    public function issueTestChecklist($issueId)
    {
        $issueId = (float)$issueId;

        try {
            $issue = Issue::load($issueId);
            if (empty($issue)) {
                return $this->error('Нет такой задачи');
            }

            // Задача запрашивается по глобальному идентификатору, поэтому права
            // на проект надо проверить здесь — так же, как это делает страница
            // задачи. Иначе текст чужой задачи ушёл бы в модель.
            $this->getProjectRequireReadPermission($issue->projectId);

            if (!IssueTestChecklistBuilder::isAvailableFor($issue, $this->getUserId())) {
                return $this->error('Чек-лист для этой задачи недоступен');
            }

            $comments = Comment::getListByInstance(LPMInstanceTypes::ISSUE, $issue->id);
            $mergeRequests = IssueTestChecklistBuilder::collectMergeRequests($comments);
            $result = IssueTestChecklistBuilder::generate($issue, $comments, $mergeRequests);

            $this->add2Answer('text', IssueTestChecklistBuilder::toCommentText($result['checklist']));
            $this->add2Answer('published', IssueTestChecklistBuilder::isPublished($comments));
        } catch (AiException $e) {
            LPMLog::exception($e, LPMLog::CH_AI, ['issueId' => $issueId]);
            return $this->error($e->getLocalizedMessage());
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }
}
