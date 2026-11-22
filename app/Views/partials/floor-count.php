<?php
/** How many dresses hang on a floor, and how many of them are free once a date is chosen. @var array $floor @var ?array $freeBy */
$n = (int) $floor['costume_count'];
echo $freeBy === null ? $n . ' ชุด' : 'ว่าง ' . (int) ($freeBy[$floor['slug']] ?? 0) . ' จาก ' . $n . ' ชุด';
