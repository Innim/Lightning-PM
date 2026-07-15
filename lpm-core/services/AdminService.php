<?php
require_once __DIR__ . '/../init.inc.php';

/**
 * Сервис, предоставляющий API для администрирования.
 * 
 * Только пользователи с ролью `User::ROLE_ADMIN`
 * могут делать запросы к этому сервису.
 */
class AdminService extends LPMBaseService
{
    /**
     * Инициирует сброс кэша.
     * @return
     */
    public function flushCache()
    {
        $cache = $this->cache();
        $cache->flush();

        return $this->answer();
    }

    /**
     * Сохраняет настройки приложения.
     *
     * Принимает ассоциативный массив `имя_опции => значение`.
     * Неизвестные опции отклоняются с ошибкой.
     *
     * @param array $data
     * @return
     */
    public function saveSettings($data)
    {
        $data = (array)$data;

        $values = [];
        foreach ($data as $field => $value) {
            switch ($field) {
                case 'allowRegistration':
                    $values[$field] = (bool)$value;
                    break;
                default:
                    return $this->error('Недопустимая настройка: ' . $field);
            }
        }

        if (empty($values)) {
            return $this->error('Не переданы настройки для сохранения');
        }

        try {
            LPMOptions::save($values);
        } catch (\Exception $e) {
            return $this->exception($e);
        }

        return $this->answer();
    }

    public function beforeFilter($calledFunc)
    {
        return parent::beforeFilter($calledFunc) && $this->checkRole(User::ROLE_ADMIN);
    }
}
