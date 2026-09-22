<?php
declare(strict_types=1);

require __DIR__ . '/../config.php';
require __DIR__ . '/../system_stats.php';

json_response(system_stats());