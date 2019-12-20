<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id'])) {
    $messages = $db->prepare('SELECT * FROM posts WHERE id=?');
    $messages->execute(array($_REQUEST['id']));
    $message = $messages->fetch();
    if ($_SESSION['id'] === $message['member_id']) {
        $del = $db->prepare('DELETE FROM posts WHERE id=?');
        $del->execute(array($_REQUEST['id']));
    }
}

header('Location: index.php');
exit();
?>