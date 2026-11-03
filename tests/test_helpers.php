<?php
require dirname(__DIR__) . '/app/bootstrap.php';
function verify(bool $result, string $label): void {
    if (!$result) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); }
    echo "PASS: {$label}\n";
}
verify(escape_html('<script>') === '&lt;script&gt;', 'HTML escaped');
verify(escape_html('"') === '&quot;', 'Attribute quotes escaped');
verify(valid_price('12.4') === '12.40', 'Price formatted');
verify(valid_price('0') === '0.00', 'Zero price allowed');
verify(valid_price('-20') === null, 'Negative price rejected');
verify(valid_price('12.345') === null, 'Overprecision rejected');
verify(valid_price('1000000') === null, 'Excessive price rejected');
verify(safe_name(str_repeat('a',32).'.mp3'), 'Generated audio filename accepted');
verify(!safe_name('../etc/passwd'), 'Path traversal rejected');
verify(!safe_name('payload.php'), 'Executable extension rejected');
