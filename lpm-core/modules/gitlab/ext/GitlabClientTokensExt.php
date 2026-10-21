<?php

/**
 * Расширение для работы с токенами доступа GitLab.
 *
 * Добавляет недостающие методы API.
 */
class GitlabClientTokensExt extends \Gitlab\Api\AbstractApi
{
    /**
     * Сведения о том токене, которым подписан сам запрос.
     *
     * Эндпоинт появился в GitLab 13.5 и отвечает только на личный токен,
     * поэтому на старом инстансе или с проектным токеном будет отказ.
     *
     * @return mixed Данные токена, в том числе `expires_at` и `revoked`.
     */
    public function current()
    {
        return $this->get('personal_access_tokens/self');
    }
}
