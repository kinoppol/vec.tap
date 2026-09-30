<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (!installed()) {
    redirect('/install.php');
}

require __DIR__ . '/app/Http.php';
dispatch();
