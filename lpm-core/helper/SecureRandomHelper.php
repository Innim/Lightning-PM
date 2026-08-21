<?php
/**
 * Генерация случайных значений, пригодных для секретов.
 *
 * Нужен отдельно от `BaseString::randomStr()`: тот построен на `rand()`,
 * последовательность которого восстанавливается по нескольким выданным
 * значениям, поэтому для токенов, ключей и идентификаторов файлов он
 * не подходит. Здесь источник - `random_bytes()`.
 */
class SecureRandomHelper
{
    /**
     * Символы, из которых собирается строка в str().
     * @var string
     */
    const ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /**
     * Возвращает случайную строку из латинских букв и цифр.
     * @param  int $length Длина строки, символов.
     * @return string
     * @throws InvalidArgumentException Если длина не положительна.
     */
    public static function str($length)
    {
        $length = (int)$length;
        if ($length <= 0) {
            throw new InvalidArgumentException('Длина случайной строки должна быть больше нуля');
        }

        $alphabetSize = strlen(self::ALPHABET);
        // Наибольшее число байтовых значений, которое делится на размер алфавита
        // без остатка. Значения выше отбрасываем: иначе первые символы алфавита
        // выпадали бы чаще остальных.
        $limit = 256 - (256 % $alphabetSize);

        $result = '';
        while (strlen($result) < $length) {
            $bytes = random_bytes($length);
            for ($i = 0; $i < $length && strlen($result) < $length; $i++) {
                $value = ord($bytes[$i]);
                if ($value >= $limit) {
                    continue;
                }

                $result .= self::ALPHABET[$value % $alphabetSize];
            }
        }

        return $result;
    }

    /**
     * Возвращает случайную строку из шестнадцатеричных символов.
     * @param  int $bytes Количество случайных байт; длина строки будет вдвое больше.
     * @return string
     * @throws InvalidArgumentException Если количество байт не положительно.
     */
    public static function hex($bytes)
    {
        $bytes = (int)$bytes;
        if ($bytes <= 0) {
            throw new InvalidArgumentException('Количество случайных байт должно быть больше нуля');
        }

        return bin2hex(random_bytes($bytes));
    }
}
