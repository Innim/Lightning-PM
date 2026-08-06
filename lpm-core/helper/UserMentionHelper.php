<?php
class UserMentionHelper
{
    /**
     * Извлекает из текста идентификаторы упомянутых пользователей.
     *
     * Упоминание имеет формат `[@имя](user:id)`.
     *
     * @param string $text Текст с возможными упоминаниями пользователей.
     * @return int[] Идентификаторы упомянутых пользователей без повторов,
     *               в порядке первого упоминания.
     */
    public static function extractMentionedUserIds($text)
    {
        $pattern = '/\[@[^\]]*?\]\(user:([0-9]+)\)/';
        if (!preg_match_all($pattern, (string)$text, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $result = [];
        foreach ($matches as $m) {
            $id = (int)$m[1];
            if ($id > 0) {
                $result[$id] = $id;
            }
        }

        return array_values($result);
    }
}
