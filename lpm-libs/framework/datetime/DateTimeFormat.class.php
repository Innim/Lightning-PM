<?php
namespace GMFramework;

/**
 * Формат вывода даты и времени
 * @author GreyMag
 * @package ru.vbinc.gm.framework.datetime
 * @author GreyMag <greymag@gmail.com>
 * @copyright 2011
 * @version 0.1
 * @link http://ru.php.net/manual/en/function.date.php
 */
class DateTimeFormat 
{
    /***********************
     * Day
     */ 
    /**
     * Day of the month, 2 digits with leading zeros
     * 01 to 31
     */
    const DAY_OF_MONTH_2 = 'd';
    /**
    * A textual representation of a day, three letters    
    * Mon through Sun
    */
    const DAY_OF_WEEK_3_LETTERS = 'D'; 
    /**
     * Day of the month without leading zeros
     * 1 to 31
     */
    const DAY_OF_MONTH = 'j';
    /**
     * A full textual representation of the day of the week    
     * Sunday through Saturday
     */
    const SAY_OF_WEEK_STRING = 'l';
    /**
     * ISO-8601 numeric representation of the day of the week (added in PHP 5.1.0)     
     * 1 (for Monday) through 7 (for Sunday)
     */
    const DAY_OF_WEEK_NUMBER_ISO8601 = 'N';
    /**
     * English ordinal suffix for the day of the month, 2 characters   
     * st, nd, rd or th. Works well with j
     */
    const DAY_OF_MONTH_SUFFIX = 'S';
    /**
     * Numeric representation of the day of the week   
     * 0 (for Sunday) through 6 (for Saturday)
     */
    const DAY_OF_WEEK_NUMBER = 'w';
    /**
     * The day of the year (starting from 0)   
     * 0 through 365
     */
    const DAY_OF_YEAR = 'z';   
    
    /***********************
     * Week
     */
    /**
     * ISO-8601 week number of year, weeks starting on Monday (added in PHP 4.1.0)     
     * Example: 42 (the 42nd week in the year)
     */
    const WEEK_NUMBER_OF_YEAR = 'W';  
    
    /***********************
     * Month
     */
    /**
     * A full textual representation of a month, such as January or March  
     * January through December
     */
    const MONTH_TEXTUAL = 'F';  
    /**
     * Numeric representation of a month, with leading zeros   
     * 01 through 12
     */
    const MONTH_NUMBER_2_DIGITS = 'm';
    /**
     * A short textual representation of a month, three letters    
     * Jan through Dec
     */
    const MONTH_TEXTUAL_SHORT = 'M';
    /**
     * Numeric representation of a month, without leading zeros    
     * 1 through 12
     */
    const MONTH_NUMBER = 'n';
    /**
     * Number of days in the given month   
     * 28 through 31
     */
    const DAYS_IN_MONTH = 't';
    
    /***********************
     * Year
     */
    /**
     * Whether it's a leap year    
     * 1 if it is a leap year, 0 otherwise.
     */
    const LEAP_YEAR = 'L';
    /**
     * ISO-8601 year number. This has the same value as Y, 
     * except that if the ISO week number (W) belongs to the previous or next year, 
     * that year is used instead. (added in PHP 5.1.0)    
     * Examples: 1999 or 2003
     */
    const YEAR_ISO8601 = 'o';
    /**
     * A full numeric representation of a year, 4 digits   
     * Examples: 1999 or 2003
     */
    const YEAR_NUMBER_4_DIGITS = 'Y';
    /**
     * A two digit representation of a year    
     * Examples: 99 or 03
     */
    const YEAR_NUMBER_2_DIGITS = 'y';    

    /***********************
     * Time
     */
    /**
     * Lowercase Ante meridiem and Post meridiem   
     * am or pm
     */
    const AM_PM_LOWERCASE = 'a';
    /**
     * Uppercase Ante meridiem and Post meridiem   
     * AM or PM
     */
    const AM_PM_UPPERCASE = 'A';
    /**
     * Swatch Internet time    
     * 000 through 999
     */
    const SWATCH_INTERNET_TIME = 'B';
    /**
     * 12-hour format of an hour without leading zeros     
     * 1 through 12
     */
    const HOUR_12_NUMBER = 'g';
    /**
     * 24-hour format of an hour without leading zeros     
     * 0 through 23
     */
    const HOUR_24_NUMBER = 'G';
    /**
     * 12-hour format of an hour with leading zeros    
     * 01 through 12
     */
    const HOUR_12_NUMBER_2_DIGITS = 'h';
    /**
     * 24-hour format of an hour with leading zeros
     * 00 through 23
     */
    const HOUR_24_NUMBER_2_DIGITS = 'H';
    /**
     * Minutes with leading zeros
     * 00 to 59
     */
    const MINUTES_OF_HOUR_2_DIGITS = 'i';
    /**
     * Seconds, with leading zeros
     * 00 through 59
     */
    const SECONDS_OF_MINUTE_2_DIGITS = 's';
    /**
     * Microseconds (added in PHP 5.2.2)   
     * Example: 654321
     */
    const MICROSECONDS = 'u';
    
    /***********************
     * Timezone
     */
    /**
     * Timezone identifier (added in PHP 5.1.0)    
     * Examples: UTC, GMT, Atlantic/Azores
     */
    const TIMEZONE_IDENTIFIER = 'e';
    /**
     * Whether or not the date is in daylight saving time  
     * 1 if Daylight Saving Time, 0 otherwise.
     */
    const DAYLIGHT_SAVING_TIME = 'I';
    /**
     * Difference to Greenwich time (GMT) in hours     
     * Example: +0200
     */
    const DIFFERENCE_2_GMT = 'O';
    /**
     * Difference to Greenwich time (GMT) with colon between hours and minutes (added in PHP 5.1.3)    
     * Example: +02:00
     */
    const DIFFERENCE_2_GMT_COLON = 'P';
    /**
     * Timezone abbreviation   
     * Examples: EST, MDT ...
     */
    const TIMEZONE_ABBREVIATION = 'T';
    /**
     * Timezone offset in seconds. The offset for timezones west of UTC is always negative, 
     * and for those east of UTC is always positive.  
     * -43200 through 50400
     */
    const TIMEZONE_OFFSET_SECONDS = 'Z';     
    
    /***********************
     * Full Date/Time
     */
    /**
     * ISO 8601 date (added in PHP 5)  
     * 2004-02-12T15:19:21+00:00
     */
    const ISO8601_DATE = 'c';
    /**
     * RFC 2822 formatted date   
     * Example: Thu, 21 Dec 2000 16:01:07 +0200
     */
    const RFC2822_DATE = 'r';
    /**
     * Seconds since the Unix Epoch (January 1 1970 00:00:00 GMT)  
     * See also time()
     */
    const UNIXTIME_SECONDS = 'U';  
}
?>