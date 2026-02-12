# PlayerWarn

[![PlayerWarn build state badge](https://poggit.pmmp.io/shield.state/PlayerWarn)](https://poggit.pmmp.io/p/PlayerWarn)
[![PlayerWarn download count badge](https://poggit.pmmp.io/shield.dl.total/PlayerWarn)](https://poggit.pmmp.io/p/PlayerWarn)

A PocketMine-MP plugin that allows server administrators to issue warnings to players and keep track of their warning history.

## Features

1. **Player Warning System**: Comprehensive system for issuing warnings to players who violate server rules or exhibit inappropriate behavior.
2. **Warning Limits**: Set configurable limits on the number of warnings a player can accumulate, triggering actions when limits are reached.
3. **Customizable Punishments**: Define various types of punishments (kick, ban, ban-ip, tempban, none) for players exceeding the warning limit.
4. **Delayed Punishments**: Schedule punishments to activate after a specified delay, allowing time for players to correct behavior.
5. **Warning Expiration**: Assign expiration dates to warnings using duration strings (e.g., `1d`, `2h30m`); players are notified when warnings expire.
6. **Discord Integration**: Integrate with Discord via webhooks to receive notifications for warning-related events and punishments.
7. **Player Notifications**: Players receive notifications upon joining, indicating their current warning count and new warnings since last login.
8. **Update Notifications**: Optionally notify administrators of plugin updates.
9. **Pending Punishments**: Queue pending punishments for offline players, ensuring they are applied upon login.
10. **Database Support**: Uses SQLite (default) or MySQL for warning storage with async operations.
11. **Multi-Language Support**: Fully customizable messages with support for multiple languages (default: English and Indonesian).

## Default Config

``` yaml
# PlayerWarn Configuration

# Do not change this (Only for internal use)!
config-version: 3.0

# Enable or disable the auto update checker notifier.
update_notifier: true

# Language setting
# Available languages: "en" (English), "id" (Indonesian)
# You can add your own language by creating a new file in the "lang" folder.
language: en

# Warning settings
warning:
  # The `expiration_check_interval` option specifies the time in seconds after which a warning expires.
  # Set to a positive integer. Default value: 180 (3 minutes)
  expiration_check_interval: 180

  # The `limit` option specifies the maximum number of warnings a player can receive before punishment is applied.
  # Set to a positive integer. Default value: 3
  limit: 3

  # The `delay` option specifies the delay time in seconds before applying the punishment.
  # If the delay is greater than 0, a warning message will be sent to the player.
  # If the delay is 0 or negative, the punishment will be applied immediately without delay.
  # Default value: 5
  delay: 5

  # The `broadcast_to_everyone` option enables or disables broadcasting warning events to all players on the server.
  # Set to true to enable broadcasting, or false to disable it.
  # When enabled, all players will be notified when a warning is issued.
  # Default value: true
  broadcast_to_everyone: true

# Punishment settings
punishment:
  # The `type` option specifies the type of punishment to apply when a player reaches the warning limit.
  # Valid options are "kick", "ban", "ban-ip", "tempban", and "none".
  # - "kick": Kicks the player from the server when the warning limit is reached.
  # - "ban": Bans the player from the server when the warning limit is reached.
  # - "ban-ip": Bans the player's IP address from the server when the warning limit is reached.
  # - "tempban": Temporarily bans the player from the server when the warning limit is reached.
  # - "none": No punishment will be applied when the warning limit is reached.
  # Default value is "none".
  type: none

  # Duration for "tempban" punishment type (e.g., "1d", "12h", "1d12h30m").
  tempban_duration: "1d"

# Database settings
database:
  # The database type. "sqlite" and "mysql" are supported.
  type: sqlite

  # Edit these settings only if you choose "sqlite".
  sqlite:
    # The file name of the database in the plugin data folder.
    # You can also put an absolute path here.
    file: warnings.sqlite
  # Edit these settings only if you choose "mysql".
  mysql:
    host: 127.0.0.1
    # Avoid using the "root" user for security reasons.
    username: root
    password: ""
    schema: your_schema
  # The maximum number of simultaneous SQL queries
  # Recommended: 1 for sqlite, 2 for MySQL. You may want to further increase this value if your MySQL connection is very slow.
  worker-limit: 1

# Discord integration settings
discord:
  # Enable or disable the Discord integration.
  # Set to true to enable the integration, or false to disable it.
  enabled: false

  # The Discord webhook URL to send messages to.
  # Replace 'YOUR_WEBHOOK_URL' with the actual URL of your Discord webhook.
  # Example: "https://discord.com/api/webhooks/123456789012345678/abcdeFGHiJKLmNOpqRSTUvwxYZ1234567890"
  webhook_url: "YOUR_WEBHOOK_URL"
```

## Discord Integration

Use this awesome website to generate valid json with built-in preview: [Discohook](https://discohook.app/), also you can send webhooks to your server with it if you just want fancy embed in your channel without any automatization.
For tags reference see [TAGS_REFERENCE.md](resources/webhooks/TAGS_REFERENCE.md)

- Webhook on Discord
  1. Go to **Server settings** -> **Webhooks** -> **Create Webhook**
  1. Setup name, avatar and the channel, where it will be posted. Copy **Webhook URL**. **Do not share! Very dangerous!**
  1. Click **Save** and then the **Done** button

- Enable Discord Integration
  1. Open the **config.yml** file.
  1. Find the **discord** section and change **enabled** to **true**.
  1. Paste the webhook URL under **webhook_url**.

## Custom Messages & Languages

PlayerWarn supports multiple languages and fully customizable messages. By default, English (`en`) and Indonesian (`id`) are included.

### Changing the Language

1. Open `config.yml`.
2. Change the `language` setting to your desired language code (e.g., `language: id`).
3. Restart the server.

### Adding a New Language

1. Navigate to the `plugin_data/PlayerWarn/lang/` directory.
2. Copy `en.yml` and rename it to your language code (e.g., `es.yml` for Spanish).
3. Open the new file and translate the messages.
4. Update `config.yml` to use your new language code (e.g., `language: es`).
5. Restart the server.

## Commands

| Command | Description | Permission | Default |
| --------- | ------------- | ------------ | --------- |
| `/warn <player> <reason> [duration]` | Issue a warning to a player | `playerwarn.command.warn` | OP |
| `/warns [player]` | View warning history | `playerwarn.command.warns` | All |
| `/clearwarns <player>` | Clear all warnings for a player | `playerwarn.command.clearwarns` | OP |
| `/delwarn <player> <id>` | Delete a specific warning by ID | `playerwarn.command.delwarn` | OP |
| `/editwarn <player> <id> <reason> [duration]` | Edit a specific warning by ID | `playerwarn.command.editwarn` | OP |
| `/listwarns` | View all players with warnings | `playerwarn.command.listwarns` | OP |

### Examples

```bash
/warn Steve griefing              # Permanent warning
/warn Steve spamming 2h30m        # Warning expires in 2 hours 30 minutes
/editwarn Steve 1 reason hacking  # Edit warning 1's reason only
/editwarn Steve 1 duration 2h30m  # Edit warning 1's duration only
/delwarn Steve 1                  # Delete warning 1
```

## Permissions

- `playerwarn.command.warn` - Use the /warn command (default: op)
- `playerwarn.command.warns` - Use the /warns command (default: true)
- `playerwarn.command.clearwarns` - Use the /clearwarns command (default: op)
- `playerwarn.command.delwarn` - Use the /delwarn command (default: op)
- `playerwarn.command.editwarn` - Use the /editwarn command (default: op)
- `playerwarn.command.listwarns` - Use the /listwarns command (default: op)
- `playerwarn.bypass` - Cannot be warned (default: op)

Grant these permissions to specific player groups or individuals using a permissions management plugin of your choice.

## Migration from v1.2.0 to v1.3.0

Upgrading from JSON-based versions? The plugin automatically migrates your data on first run. The old file is renamed to `warnings.json.migrated`.

## TODO Features

- **WarnHistory**: provide advanced warning history, analytics, and reporting features.

## Additional Notes

- If you find bugs or want to give suggestions, please visit the [issues page](https://github.com/AIPTU/PlayerWarn/issues).
- We accept all contributions! If you want to contribute, please make a pull request on the [pull requests page](https://github.com/AIPTU/PlayerWarn/pulls).
- Icons made from [www.flaticon.com](https://www.flaticon.com)
