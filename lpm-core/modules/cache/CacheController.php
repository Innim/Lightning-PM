<?php
/**
 * Контроллер для работы с кэшем.
 */
class CacheController
{
    const OWNCLOUD_SHARED_FILE_TYPE_PREFIX = 'owncloud_shared_file_type_prefix-';

    /**
     * @var Memcached
     */
    private $_memcached;

    public function __construct()
    {
        if (defined('MEMCACHED_HOST') && defined('MEMCACHED_PORT') && !empty(MEMCACHED_HOST)) {
            $this->_memcached = new Memcached();
            $this->_memcached->addServer(MEMCACHED_HOST, MEMCACHED_PORT);
        }
    }

    public function isEnabled()
    {
        return !empty($this->_memcached);
    }

    /**
     * Возвращает сохраненное значение.
     *
     * Если кжш выключен, значения нет или у него истек срок жизни,
     * то вернется false.
     */
    public function get($key)
    {
        if (!$this->isEnabled()) {
            return false;
        }

        return $this->_memcached->get($key);
    }

    /**
     * Сохраняет значение в кэше.
     * @param string $key Ключ.
     * @param mixed $value Значение.
     * @param int $expiredAt Время (unixtime, s) истечения значения.
     * @return bool Вернется true, если данные были записаны, иначе false.
     */
    public function set($key, $value, $expiredAt = 0)
    {
        if (!$this->isEnabled()) {
            return false;
        }

        return $this->_memcached->set($key, $value, $expiredAt);
    }

    public function getOwncloudSharedFileType($url)
    {
        return $this->get($this->getOwncloudSharedFileTypeKey($url));
    }

    public function setOwncloudSharedFileType($url, $value)
    {
        return $this->set($this->getOwncloudSharedFileTypeKey($url), $value);
    }

    private function getOwncloudSharedFileTypeKey($url)
    {
        return self::OWNCLOUD_SHARED_FILE_TYPE_PREFIX . md5($url);
    }
}
