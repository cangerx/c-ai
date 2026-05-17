#!/bin/bash
cd "$(dirname "$0")"
pkill -f "task:worker" 2>/dev/null || true
sleep 1
nohup php artisan task:worker --max-retries=3 >> storage/logs/worker.log 2>&1 &
nohup php artisan task:worker --max-retries=3 >> storage/logs/worker.log 2>&1 &
echo "Worker 已重启 ✓"
ps aux | grep task:worker | grep -v grep
