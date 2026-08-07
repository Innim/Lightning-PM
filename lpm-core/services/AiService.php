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

            $commentsCount = IssueSummaryBuilder::countMeaningful($comments);
            $html = $this->getHtml(function () use ($issue, $summary, $sourceHash, $commentsCount) {
                PagePrinter::aiIssueSummary($issue, $summary, $sourceHash, $commentsCount);
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
}
