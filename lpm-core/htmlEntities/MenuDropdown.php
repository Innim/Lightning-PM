<?php
/**
 * Выпадающий список, который показывается рядом с пунктом меню.
 *
 * Сам пункт меню при этом остаётся обычной ссылкой на свою страницу -
 * список открывается отдельной кнопкой.
 */
class MenuDropdown
{
    /**
     * Идентификатор элемента-переключателя, на него ссылается список.
     * @var string
     */
    public $id;

    /**
     * Подпись переключателя - для подсказки и программ чтения с экрана.
     * @var string
     */
    public $label;

    /**
     * Пункты списка.
     * @var array<Link>
     */
    public $items;

    /**
     * Завершающий пункт, отделённый от остальных чертой. `null`, если его нет.
     * @var Link|null
     */
    public $footer;

    /**
     * @param string      $id     Идентификатор элемента-переключателя.
     * @param string      $label  Подпись переключателя.
     * @param array<Link> $items  Пункты списка.
     * @param Link|null   $footer Завершающий пункт списка.
     */
    public function __construct($id, $label, array $items, Link $footer = null)
    {
        $this->id     = $id;
        $this->label  = $label;
        $this->items  = $items;
        $this->footer = $footer;
    }
}
