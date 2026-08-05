<?php
class OwnUrlHelper
{
   public static function getIssueUrlPattern()
   {
        $host = LightningEngine::getHost();
        $protocols = ['http', 'https'];

        return '(?:' . implode('|', $protocols) . '):\/\/' . $host . '\/project\/([a-zA-Z0-9_-]*)\/issue\/(\d*)\/?(?:#(?:comment-\d+)?)?';
   }

   /**
    * Загружает задачу по ссылке на неё.
    *
    * @param string $url Ссылка на задачу.
    * @return Issue|null Задача или null, если ссылка не распознана либо задача не найдена.
    */
   public static function loadIssueByUrl($url)
   {
        $issues = self::extractLinkedIssues($url);
        return empty($issues) ? null : $issues[0];
   }

   /**
    * Извлекает из текста задачи, на которые в нём есть ссылки.
    *
    * @param string $text Текст с возможными ссылками на задачи.
    * @return Issue[] Найденные задачи без повторов, в порядке первого упоминания.
    */
   public static function extractLinkedIssues($text)
   {
        $pattern = '/' . self::getIssueUrlPattern() . '/';
        if (!preg_match_all($pattern, (string)$text, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $result = [];
        foreach ($matches as $m) {
            $issue = self::loadIssue($m[1], (int)$m[2]);
            if ($issue !== null) {
                $result[$issue->getID()] = $issue;
            }
        }

        return array_values($result);
   }

   private static function loadIssue($projectUid, $idInProject)
   {
        if (empty($projectUid) || $idInProject <= 0) {
            return null;
        }

        $project = Project::load($projectUid);
        if (empty($project)) {
            return null;
        }

        $issue = Issue::loadByIdInProject($project->id, $idInProject);
        return empty($issue) ? null : $issue;
   }
}
