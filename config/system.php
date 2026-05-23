<?php

return [
    'backup_dir' => env('SYSTEM_BACKUP_DIR', storage_path('app/private/backups')),
    'backup_keep' => (int) env('SYSTEM_BACKUP_KEEP', 10),
    'backup_upload_max_mb' => (int) env('SYSTEM_BACKUP_UPLOAD_MAX_MB', 512),
    'frontend_backup_keep' => (int) env('FRONTEND_BACKUP_KEEP', 3),
    'allow_upgrade_without_backup' => (bool) env('UPGRADE_ALLOW_WITHOUT_BACKUP', false),
    'backend_repo' => env('BACKEND_REPO', 'cangerx/c-ai'),
    'frontend_repo' => env('FRONTEND_REPO', 'cangerx/cang-ai-web'),
    'frontend_pm2_name' => env('FRONTEND_PM2_NAME', 'cang-ai-web'),
    'frontend_port' => (int) env('FRONTEND_PORT', 3000),
];
