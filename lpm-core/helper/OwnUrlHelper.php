<?php 
class OwnUrlHelper
{
   public static function getIssueUrlPattern()
   {
        $host = LightningEngine::getHost();
        $protocols = ['http', 'https'];

        return '(?:' . implode('|', $protocols) . '):\/\/' . $host . '\/project\/([a-zA-Z0-9_-]*)\/issue\/(\d*)\/?(?:#(?:comment-\d+)?)?';
   }
}