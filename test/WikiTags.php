<?php
require '../Hawk.php';

$hawk = new Hawk();

if ($file = fopen('data/WikiTags.md', 'r')) {
    $fileContent = fread($file, filesize('data/WikiTags.md'));

    if (!empty($_GET))
        print_r($_GET);

    $hawk->setInternalLinkBaseUrl('http://localhost/HawkMDP/test/WikiTags.php?');
    echo $hawk->text($fileContent);
}

else {
    die('Unable to load data file.');
}