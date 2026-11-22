<?php
/** What the date desk says on a listing: the days the dress is held for, or how to begin. @var array $filters @var ?array $window */
if ($filters['date'] && $window): ?>
รับชุด <?= e(thai_date($window[0], false, false)) ?> · คืนชุด <?= e(thai_date($window[1], false, false)) ?>
<span class="faint">ก่อนและหลังวันงาน 1 วัน</span>
<?php else: ?>
เลือกวันงาน แล้วทุกชุดจะบอกว่าว่างหรือไม่ว่างในวันนั้น
<?php endif;
