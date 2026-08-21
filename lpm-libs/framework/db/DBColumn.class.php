<?php
namespace GMFramework;

/**
 * Имя колонки там, где конструктор запросов ожидает значение.
 *
 * Нужен, чтобы отличить сравнение колонки с колонкой
 * <code>'ON' => ['`fl`.`fileId`' => new DBColumn('f.fileId')]</code>
 * от сравнения со значением, которое надо экранировать.
 * В моделях короче через <code>LPMBaseObject::col()</code>:
 * <code>'ON' => ['`fl`.`fileId`' => self::col('f.fileId')]</code>.
 *
 * Признаком не может быть ни префикс в строке, ни ключ массива: значение
 * приходит в том числе из запроса, а строку и массив пользователь задаёт сам,
 * то есть такой признак подделывается. Объект из запроса не появляется -
 * суперглобалы содержат только строки и массивы, json_decode даёт
 * скаляры, массивы и stdClass.
 *
 * @package ru.vbinc.gm.framework.db
 */
class DBColumn
{
    /**
     * @var string
     */
    private $_name;

    /**
     * @param string $name Имя колонки, при необходимости с именем таблицы
     *                     или алиасом через точку: <code>fileId</code>,
     *                     <code>f.fileId</code>. Обратные апострофы
     *                     подставляются сами.
     * @throws Exception Если имя не похоже на имя колонки.
     */
    public function __construct($name)
    {
        $name = (string)$name;

        if (!preg_match('/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)?$/', $name)) {
            throw new Exception('Wrong column name: ' . $name);
        }

        $this->_name = $name;
    }

    /**
     * Возвращает имя колонки в виде, готовом для подстановки в запрос.
     * @return string
     */
    public function getSql()
    {
        return '`' . str_replace('.', '`.`', $this->_name) . '`';
    }
}
