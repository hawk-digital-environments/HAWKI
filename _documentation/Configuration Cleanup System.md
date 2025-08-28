# HAWKI Configuration Cleanup System

The HAWKI Configuration Cleanup System provides a structured method for managing and cleaning up deprecated configuration entries in the database.

## Components

### 1. `config/deprecated.php`
Central configuration file for deprecated keys:
- **Versioned Entries**: Organized by version keys (e.g. `v2.0.1`)
- **Cleanup Rules**: Configuration for backup, confirmation and logging
- **Auto-Detection**: Patterns for automatic detection of deprecated keys

### 2. `app/Console/Commands/CleanupDeprecatedSettings.php`
Artisan command for manual cleanup:
```bash
# Dry-Run: Shows what would be deleted
php artisan config:cleanup-deprecated --dry-run

# With Auto-Detection
php artisan config:cleanup-deprecated --dry-run --auto-detect

# Specific Version only
php artisan config:cleanup-deprecated --target-version=v2.0.1

# Force (without confirmation)
php artisan config:cleanup-deprecated --force
```

### 3. Migration for automatic cleanup
Migration performs cleanup automatically during deployment:
```bash
php artisan migrate
```

## Usage

### Adding new deprecated keys

1. **Edit config/deprecated.php**:
```php
'v2.1.0' => [
    'old_setting_key' => 'Reason for deprecation',
    'another_old_key' => 'Replaced by new structure',
],
```

2. **Create migration for automatic cleanup**:
```bash
php artisan make:migration cleanup_deprecated_settings_v2_1_0
```

3. **Adapt migration code** (following the template of existing migration)

### Manual cleanup

```bash
# Check what would be deleted
php artisan config:cleanup-deprecated --dry-run --auto-detect

# Perform actual cleanup
php artisan config:cleanup-deprecated

# Cleanup only specific version
php artisan config:cleanup-deprecated --target-version=v2.0.1
```

### Auto-Detection Patterns

The system can automatically detect potentially deprecated keys:

**Prefixes**: `old_`, `legacy_`, `deprecated_`, `temp_`
**Contains**: `_old_`, `_legacy_`, `_deprecated_`, `_temp_`

Example:
- `old_user_setting` → automatically detected
- `auth_legacy_config` → automatically detected  
- `temp_migration_key` → automatically detected

## Security Features

### Automatic Backups
- Backup before every deletion (enabled by default)
- Stored in `storage/app/config_backups/`
- JSON format with all metadata

### Logging
- All cleanup operations are logged
- Including deleted values for recovery
- Migration logs vs. manual command logs

### Confirmations
- Interactive confirmation before deletion (can be disabled with `--force`)
- Dry-run mode for safe preview
- Detailed display of what will be deleted

## Backup & Recovery

### Backup Structure
```json
{
    "migration": "2025_08_28_151412_cleanup_deprecated_settings_v2_0_1",
    "timestamp": "2025-08-28_15-14-12", 
    "deprecated_keys": {
        "old_key": {
            "key": "old_key",
            "value": "old_value",
            "group": "authentication",
            "reason": "Replaced by new system",
            "created_at": "2025-01-01T00:00:00",
            "updated_at": "2025-08-01T00:00:00"
        }
    }
}
```

### Recovery
If necessary, values can be manually restored to the database from backup files:

```bash
# Find backup files
ls storage/app/config_backups/

# Restore with Tinker
php artisan tinker
```

## Best Practices

### 1. Versioning
- Use semantic versioning for deprecated.php entries
- Document reason for deprecation thoroughly
- Group related changes together

### 2. Testing
- Always test with `--dry-run` first
- Check backups before production cleanup
- Use `--auto-detect` for forgotten keys

### 3. Deployment
- Migrations perform automatic cleanup
- Manual cleanup for ad-hoc cases
- Check backup files before deployment

### 4. Monitoring
- Check log files for cleanup operations
- Monitor backup directory
- Regularly check auto-detection results

## Advanced Configuration

### Customizing cleanup rules
```php
'cleanup_rules' => [
    'backup_before_delete' => true,          // Create backup
    'backup_path' => storage_path('app/config_backups'),
    'require_confirmation' => true,          // Confirmation required
    'log_operations' => true,                // Log operations
],
```

### Customizing Auto-Detection
```php
'auto_detect_patterns' => [
    'prefixes' => ['old_', 'legacy_', 'test_'],
    'contains' => ['_backup_', '_migration_'],
],
```

## Troubleshooting

### Command Errors
- Check `config/deprecated.php` syntax
- Ensure AppSetting Model is available
- Check database connection

### Backup Problems  
- Check write permissions for `storage/app/config_backups/`
- Ensure directory exists
- Check disk space

### Migration Errors
- Check that `config/deprecated.php` exists
- Ensure AppSetting Model is imported
- Check log files for details

## Example Workflow

```bash
# 1. Define new deprecated keys in config/deprecated.php
# 2. Test what would be deleted
php artisan config:cleanup-deprecated --dry-run --auto-detect

# 3. Create migration if needed
php artisan make:migration cleanup_deprecated_settings_v2_1_0

# 4. Adapt and test migration
php artisan migrate --dry-run

# 5. Deploy to production
php artisan migrate
```
