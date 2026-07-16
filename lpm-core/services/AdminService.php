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
                case 'title':
                case 'subtitle':
                case 'fromName':
                case 'emailSubscript':
                    $values[$field] = trim((string)$value);
                    break;
                case 'fromEmail':
                    $email = trim((string)$value);
                    if ($email !== '' && !Validation::checkEmail($email)) {
                        return $this->error('Некорректный email отправителя');
                    }
                    $values[$field] = $email;
                    break;
                case 'cookieExpire':
                    $days = (int)$value;
                    if ($days < 1) {
                        return $this->error('Срок действия сессии должен быть не менее 1 дня');
                    }
                    $values[$field] = $days;
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
