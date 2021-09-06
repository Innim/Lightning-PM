<?php
/**
 * Базовый класс внешнего API.
 */
abstract class ExternalApi
{
    private $_engine;
    private $_uid;

    public function __construct(LightningEngine $engine, $uid)
    {
        $this->_engine = $engine;
        $this->_uid = $uid;
    }

    /**
     * Уникальный идентификатор API.
     *
     * Используется в адресе.
     * @return string
     */
    public function getUid()
    {
        return $this->_uid;
    }

    /**
     * Возвращает URL для доступа к API.
     * @return string
     */
    public function getUrl()
    {
        return Link::getUrl(LightningEngine::API_PATH, [$this->getUid()]);
    }

    /**
     * Запускает обработку запроса API.
     * @param  string $input Входящие данные (php://input).
     * @return string Строка ответа, которая будет распечатана.
     */
    abstract public function run($input);

    protected function engine()
    {
        return $this->_engine;
    }
}
