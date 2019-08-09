<?php
# Получаю URL страницы, обрабатываю его, чтобы получить номер проекта

$URL = $_POST['Check'];
$arrayUrl = explode("/", $URL);
$numberProject = $arrayUrl[4];

try {
    $PDO = new PDO("mysql:host=localhost;dbname=lpm_schema", "root", "5513");

    $STH = $PDO->query('SELECT idPost FROM lpm_tester');
    $projectarray = $STH->fetchAll(PDO::FETCH_COLUMN, 0);

    if(in_array($numberProject, $projectarray)){
        $WTH = $PDO->query("SELECT nameUser FROM lpm_tester WHERE idPost='$numberProject' ");
        $tb = $WTH->fetch(PDO::FETCH_COLUMN, 0);
        if($tb == ""){
            echo 'NotFoundProgect';
        } else {
            echo $tb;
        }
    } else {
        echo 'NotFoundProgect';
    }
    
} catch(PDOException $e) {
    echo $e->getMessage();
}



