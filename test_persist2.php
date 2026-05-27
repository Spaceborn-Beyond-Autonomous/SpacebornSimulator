<?php
require 'c:\xampp\htdocs\SpaceBorn\SpaceBorn\auth\db.php';
require 'c:\xampp\htdocs\SpaceBorn\SpaceBorn\includes\simulator_launch.php';

$email = 'maiadarshkumar@gmail.com';
$user = $db->users->findOne(['email' => $email]);

$row = sb_current_user_record($user);
$persistState = function (array $fields) use ($row): void {
    $email = (string) ($row['email'] ?? ($_SESSION['email'] ?? ''));
    var_dump("Email:", $email);
    var_dump("Has GLOBALS db:", isset($GLOBALS['db']));
    var_dump("Has GLOBALS db->users:", isset($GLOBALS['db']->users));
    if ($email === '' || !isset($GLOBALS['db']) || !isset($GLOBALS['db']->users)) {
        echo "Aborted!\n";
        return;
    }
    echo "Updating...\n";
    $res = $GLOBALS['db']->users->updateOne(
        ['email' => $email],
        ['$set' => $fields]
    );
    var_dump("Matched:", $res->getMatchedCount(), "Modified:", $res->getModifiedCount());
};

$persistState(['sub_started' => true]);
