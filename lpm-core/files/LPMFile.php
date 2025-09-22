<?php

use GMFramework\BaseString;
use GMFramework\FileSystemUtils;

/**
 * Uploaded file metadata.
 */
class LPMFile extends LPMBaseObject
{
    public static function load($fileId)
    {
        $fileId = (int)$fileId;
        if ($fileId <= 0) {
            return null;
        }

        return self::loadAndParseSingle([
            'SELECT' => '*',
            'FROM'   => LPMTables::FILES,
            'WHERE'  => [
                'fileId' => $fileId,
            ],
            'LIMIT'  => 1,
        ], __CLASS__);
    }

    public static function loadByUid($uid)
    {
        $uid = trim((string)$uid);
        if ($uid === '') {
            return null;
        }

        return self::loadAndParseSingle([
            'SELECT' => '*',
            'FROM'   => LPMTables::FILES,
            'WHERE'  => [
                'uid' => $uid,
            ],
            'LIMIT'  => 1,
        ], __CLASS__);
    }

    public static function loadListByInstance($itemType, $itemId, $fileIds = null)
    {
        $itemType = (int)$itemType;
        $itemId = (int)$itemId;

        $where = [
            '`f`.`deleted`' => 0,
            '`fl`.`itemType`' => $itemType,
            '`fl`.`itemId`' => $itemId,
        ];

        if (is_array($fileIds)) {
            $ids = array_filter(array_map('intval', $fileIds));
            if (empty($ids)) {
                return [];
            }
            
            $where['`f`.`fileId`'] = $ids;
        }

        return self::loadAndParseV2([
            'SELECT' => '`f`.*',
            'FROM'   => LPMTables::FILES,
            'AS'     => 'f',
            'JOINS'  => [
                [
                    'INNER JOIN' => LPMTables::FILE_LINKS,
                    'AS'         => 'fl',
                    'ON'         => ['`fl`.`fileId`' => '`f`.`fileId`'],
                ],
            ],
            'WHERE'  => $where,
            'ORDER BY' => '`fl`.`created` ASC',
        ], __CLASS__);
    }

    public static function countByInstance($itemType, $itemId)
    {
        if ($itemType <= 0 || $itemId <= 0) {
            return 0;
        }

        $row = self::buildAndExecuteSingle([
            'SELECT' => 'COUNT(*) AS `count`',
            'FROM'   => LPMTables::FILES,
            'AS'     => 'f',
            'JOINS'  => [
                [
                    'INNER JOIN' => LPMTables::FILE_LINKS,
                    'AS'         => 'fl',
                    'ON'         => '`fl`.`fileId` = `f`.`fileId`',
                ],
            ],
            'WHERE'  => [
                '`f`.`deleted` = 0',
                '`fl`.`itemType` = ' . (int)$itemType,
                '`fl`.`itemId` = ' . (int)$itemId,
            ],
        ]);

        return (int)$row['count'];
    }

    public static function create($itemType, $itemId, $userId, $origName, $mimeType, $size, $relativePath, $uid = null)
    {
        $uid = $uid ?: self::generateUid();

        $record = [
            'uid'       => $uid,
            'userId'    => (int)$userId,
            'path'      => $relativePath,
            'origName'  => $origName,
            'mimeType'  => $mimeType,
            'size'      => (int)$size,
        ];

        $hash = [
            'INSERT' => $record,
            'INTO'   => LPMTables::FILES,
        ];

        self::buildAndSaveToDbV2($hash);
        $fileId = self::getDB()->insert_id;

        try {
            self::linkToInstance($fileId, $itemType, $itemId);
        } catch (\Exception $e) {
            self::removeFileRecord($fileId);
            throw $e;
        }

        return self::load($fileId);
    }

    public static function delete($itemType, $itemId, array $fileIds)
    {
        $itemType = (int)$itemType;
        $itemId = (int)$itemId;
        $ids = array_filter(array_map('intval', $fileIds));
        if (empty($ids)) {
            return;
        }

        $existing = self::loadListByInstance($itemType, $itemId, $ids);

        if (empty($existing)) {
            return;
        }

        self::buildAndSaveToDbV2([
            'DELETE' => LPMTables::FILE_LINKS,
            'WHERE'  => [
                'fileId' => $ids,
                'itemType' => $itemType,
                'itemId' => $itemId,
            ],
        ]);

        foreach ($existing as $file) {
            if (!self::hasActiveLinks($file->fileId)) {
                FileSystemUtils::remove($file->getAbsolutePath(), false);
                self::markFileDeleted($file->fileId);
            }
        }
    }

    public static function linkToInstance($fileId, $itemType, $itemId)
    {
        $fileId = (int)$fileId;
        $itemType = (int)$itemType;
        $itemId = (int)$itemId;

        if ($fileId <= 0 || $itemType <= 0 || $itemId <= 0) {
            throw new \InvalidArgumentException('Invalid file link arguments');
        }

        self::buildAndSaveToDbV2([
            'INSERT' => [
                'fileId'   => $fileId,
                'itemType' => $itemType,
                'itemId'   => $itemId,
            ],
            'INTO'   => LPMTables::FILE_LINKS,
            'IGNORE' => true,
        ]);
    }

    public static function loadInstanceLinks($fileId)
    {
        $fileId = (int)$fileId;
        if ($fileId <= 0) {
            return [];
        }

        $res = self::buildAndExecute([
            'SELECT' => ['itemType', 'itemId'],
            'FROM'   => LPMTables::FILE_LINKS,
            'WHERE'  => ['fileId' => $fileId],
            'ORDER BY' => '`created` ASC',
        ]);

        $links = [];
        while ($row = $res->fetch_assoc()) {
            $links[] = [
                'itemType' => (int)$row['itemType'],
                'itemId'   => (int)$row['itemId'],
            ];
        }

        return $links;
    }

    private static function hasActiveLinks($fileId)
    {
        return self::countActiveLinks($fileId) > 0;
    }

    private static function countActiveLinks($fileId)
    {
        $row = self::buildAndExecuteSingle([
            'SELECT' => 'COUNT(*) AS `count`',
            'FROM'   => LPMTables::FILE_LINKS,
            'WHERE'  => ['fileId' => (int)$fileId],
        ]);

        return (int)$row['count'];
    }

    private static function markFileDeleted($fileId)
    {
        self::buildAndSaveToDbV2([
            'UPDATE' => LPMTables::FILES,
            'SET'    => [
                'deleted' => 1,
            ],
            'WHERE'  => ['`fileId` = ' . (int)$fileId],
        ]);
    }

    private static function removeFileRecord($fileId)
    {
        self::buildAndExecute([
            'DELETE' => LPMTables::FILES,
            'WHERE'  => ['fileId' => (int)$fileId],
        ]);
    }
    
    private static function generateUid()
    {
        do {
            $uid = BaseString::randomStr(32);
        } while (self::loadByUid($uid));

        return $uid;
    }

    /**
     * Maximum allowed size for a single uploaded file (in megabytes).
     * @var int
     */
    const MAX_SIZE_MB = 50;

    /**
     * Identifier of the file record.
     * @var int
     */
    public $fileId;

    /**
     * Unique identifier used in download URLs.
     * @var string
     */
    public $uid;

    /**
     * Identifier of user who uploaded the file.
     * @var int
     */
    public $userId;

    /**
     * Relative path (within {@see FILES_DIR}) to the stored file.
     * @var string
     */
    public $path;

    /**
     * Original filename provided by the user.
     * @var string
     */
    public $origName;

    /**
     * Detected MIME type.
     * @var string
     */
    public $mimeType;

    /**
     * File size in bytes.
     * @var int
     */
    public $size;

    /**
     * Upload date (unix timestamp).
     * @var float
     */
    public $created ;

    /**
     * Deletion flag.
     * @var bool
     */
    public $deleted;

    public function __construct($id = 0)
    {
        parent::__construct();

        $this->fileId = $id;

        $this->_typeConverter->addFloatVars('fileId', 'userId', 'size');
        $this->_typeConverter->addBoolVars('deleted');
        $this->addDateTimeFields('created');
        $this->addClientFields('fileId', 'uid', 'mimeType', 'size', 'created');
    }

    public function getDownloadUrl()
    {
        return Link::getFileUrl($this->uid);
    }

    public function getAbsolutePath()
    {
        return FileUploadManager::getAbsolutePath($this->path);
    }

    public function getClientObject($addFields = null)
    {
        $obj = parent::getClientObject($addFields);
        $obj->url = $this->getDownloadUrl();
        $obj->name = $this->origName;
        $obj->sizeFormatted = FileSizeFormatter::format($this->size);
        return $obj;
    }
}
