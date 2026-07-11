#!/bin/sh
cd /var/www/html
for f in local/academy/classes/discount_manager.php local/payments/classes/manager.php local/academy/tests/discount_manager_test.php; do
  OUT=$(php -l "$f" 2>&1); if [ $? -ne 0 ]; then echo "FAIL: $f"; echo "$OUT"; else echo "ok: $f"; fi
done
