<?php
/**
 * Контроллер для работы с кэшем.
 */
class CacheController
{
    const OWNCLOUD_SHARED_FILE_TYPE_PREFIX = 'owncloud_shared_file_type_prefix-';
    const IMAGE_CACHED_PREVIEW_PREFIX = 'image_cached_preview_prefix-';
    const USER_SLACK_AVATAR_PREFIX = 'user_slack_avatar-';

    /**
     * @var Memcached
     */
    private $_memcached;

    /**
     * @var ImageCacheController
     */
    private $_images;

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
     * Сбрасывает кэш.
     * 
     * При этом данные не очищаются, они просто помечаются как устаревшие.
     */
    public function flush() 
    {
        if ($this->isEnabled()) {
            $this->_memcached->flush();
        }
    }

    /**
     * Возвращает сохраненное значение.
     *
     * Если кэш выключен, значения нет или у него истек срок жизни,
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

    /**
     * Возвращает URL аватара пользователя из Slack.
     * 
     * @return string|false URL аватара.
     * Если значения нет, оно истекло или кэш выключен, 
     * то вернется false. 
     */
    public function getUserSlackAvatarUrl($userId)
    {
        return $this->get($this->getUserSlackAvatarUrlKey($userId));
    }

    public function setUserSlackAvatarUrl($userId, $url)
    {
        return $this->set($this->getUserSlackAvatarUrlKey($userId), $url);
    }

    /**
     * Возвращает URL до превью изображения по URL.
     * 
     * Если превью нет, то оно будет создано.
     * Если не удалось скачать изображение, 
     * то превью не будет сделано и URL будет помечен
     * как испорченный.
     * 
     * @param string $url исходного изображения
     * @return string|null 
     */
    public function getImageCachedPreview($url)
    {
        $key = $this->getImageCachedPreviewKey($url);
        $res = $this->get($key);

        // Важна проверка именно на false, потому что null важное значение
        if ($res === false) {
            $res = $this->images()->createCache($url);
            $this->set($key, $res, 24 * 3600);
        }

        return $res;
    }

    private function getOwncloudSharedFileTypeKey($url)
    {
        return self::OWNCLOUD_SHARED_FILE_TYPE_PREFIX . md5($url);
    }

    private function getImageCachedPreviewKey($url)
    {
        return self::IMAGE_CACHED_PREVIEW_PREFIX . md5($url);
    }

    private function getUserSlackAvatarUrlKey($userId)
    {
        return self::USER_SLACK_AVATAR_PREFIX . $userId;
    }

    private function images() 
    {
        if (empty($this->_images)) {
            $this->_images = new ImageCacheController();
        }

        return $this->_images;
    }
}
