# Groupchat Invitation Email Notifications

## Feature Implementation

### Overview
Added email notification system for groupchat invitations. When users are invited to join a groupchat room, they now receive an email notification (if enabled in configuration).

### Configuration
- **Config Key**: `hawki.send_groupchat_invitation_mails`
- **Default**: `true`
- **Admin Panel**: Configurable via Orchid admin panel under System Settings

### How It Works

1. **New Invitations Only**: Email notifications are sent only for new invitations, not for updates to existing invitations
2. **Conditional Sending**: Emails are only sent if the configuration is enabled
3. **Queue-Based**: Emails are dispatched via Laravel's queue system for optimal performance
4. **Logging**: All email sending attempts are logged for debugging purposes

### Email Content

The invitation email includes:
- Inviter's name
- Room name
- Room description (if available and not encrypted)
- Direct link to the groupchat room
- Information about HAWKI features

### Technical Details

#### Modified Files
- `app/Http/Controllers/InvitationController.php`:
  - Extended `storeInvitations()` method to send email notifications
  - Added private `sendInvitationEmail()` method for email handling

#### Email Template
- Template: `resources/views/emails/invitation.blade.php`
- Updated to differentiate between:
  - External invitations (with expiring signed URLs)
  - Internal invitations (direct room links without expiration)

#### Testing
- New test suite: `tests/Feature/GroupchatInvitationMailTest.php`
- Tests cover:
  - Email queuing when feature is enabled
  - No email when feature is disabled
  - No email for invitation updates
  - Correct email data
  - Multiple invitations handling

### Usage Example

```php
// In InvitationController@storeInvitations
// When a new invitation is created:
if ($isNewInvitation && config('hawki.send_groupchat_invitation_mails')) {
    $this->sendInvitationEmail($username, $room, $inviter);
}
```

### Admin Configuration

Admins can enable/disable this feature via:
1. Orchid Admin Panel → System Settings
2. Toggle "Send email notifications for group chat invitations"
3. Or manually in `config/hawki.php`:
   ```php
   'send_groupchat_invitation_mails' => true,
   ```

### Error Handling

- If invited user doesn't exist or has no email: Warning logged, no email sent
- If email sending fails: Error logged with details
- Encryption-related failures are caught and logged

### Performance Considerations

- Emails are queued using `SendEmailJob` on the 'mails' queue
- Non-blocking operation - doesn't slow down invitation creation
- Background processing via Laravel queue workers
- Queue worker processes: `default`, `mails`, `message_broadcast`

## Related Configuration Keys

- `hawki.send_groupchat_invitation_mails` - Enable/disable invitation emails
- `mail.*` - General email configuration (SMTP, driver, etc.)

## Future Enhancements

Potential improvements:
- Email templates with custom branding
- Digest emails for multiple invitations
- Invitation reminder emails
- Customizable email content per room

## Fixes

### 2025-12-09: Queue Name & Route Fixes
- **Fixed**: Changed queue from `emails` to `mails` to match queue worker configuration
- **Fixed**: Changed route generation from `route('groupchat')` to `url('/groupchat/' . $slug)` since route is not named
- **Added**: Debug logging for invitation creation and email queuing
- **Added**: Troubleshooting guide and test script
