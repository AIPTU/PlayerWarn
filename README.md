# PlayerWarn

A PocketMine-MP plugin that allows server administrators to issue warnings to players and keep track of their warning history.

# Features

1. **Player Warning System**: A comprehensive system for issuing warnings to players who violate server rules or exhibit inappropriate behavior.
2. **Warning Limits**: Set configurable limits on the number of warnings a player can accumulate, triggering actions when limits are reached.
3. **Customizable Punishments**: Define various types of punishments for players exceeding the warning limit, including kicking, banning, and IP banning.
4. **Delayed Punishments**: Schedule punishments to activate after a specified delay, allowing time for players to correct behavior.
5. **Warning Expiration**: Assign expiration dates to warnings; players are notified when warnings expire.
6. **Discord Integration**: Integrate with Discord via webhooks to receive notifications for warning-related events, punishments, and more.
7. **Player Notifications**: Players receive notifications upon joining, indicating their current warning count, new warnings since last login, and active punishments.
8. **Update Notifications**: Optionally notify administrators of plugin updates to ensure the latest version is being used.
9. **Configurable Warning Messages**: Customize warning messages sent to players upon receiving a warning or upon warning expiration.
10. **Configurable Punishment Messages**: Define custom messages for each punishment type, displayed when punishments are applied.
11. **Pending Punishments**: Queue pending punishments for offline players, ensuring they are applied upon login.
12. **Ease of Management**: Use commands to issue warnings, remove warnings, clear all warnings for a player, and view warning history.
13. **Flexible Configuration**: Highly customizable configuration options to adapt the warning and punishment system to specific server needs.
14. **Event Handling**: Utilize event listeners to track player actions and trigger appropriate responses based on warnings and punishments.
15. **Comprehensive Logging**: Log errors, warnings, and important events to maintain a record of significant activities related to warnings and punishments.


# Default Config
``` yaml
# PlayerWarn Configuration

# Do not change this (Only for internal use)!
config-version: 1.0

# Enable or disable the auto update checker notifier.
update_notifier: true

# Warning settings
warning:
  # The `limit` option specifies the maximum number of warnings a player can receive before punishment is applied.
  # Set to a positive integer. Default value: 3
  limit: 3

  # The `delay` option specifies the delay time in seconds before applying the punishment.
  # If the delay is greater than 0, a warning message will be sent to the player.
  # If the delay is 0 or negative, the punishment will be applied immediately without delay.
  # Default value: 5
  delay: 5

  # The `message` option specifies the warning message template to be sent to the player.
  # The `{delay}` placeholder will be replaced with the actual delay time in seconds.
  # You can use color codes by using "§" or "&" before the color code.
  # Default value: '&cYou have reached the warning limit. You will be punished in {delay} seconds.'
  message: '&cYou have reached the warning limit. You will be punished in {delay} seconds.'

# Punishment settings
punishment:
  # The `type` option specifies the type of punishment to apply when a player reaches the warning limit.
  # Valid options are "kick", "ban", "ban-ip", and "none".
  # - "kick": Kicks the player from the server when the warning limit is reached.
  # - "ban": Bans the player from the server when the warning limit is reached.
  # - "ban-ip": Bans the player's IP address from the server when the warning limit is reached.
  # - "none": No punishment will be applied when the warning limit is reached.
  # Default value is "none".
  type: none

  # Custom punishment messages for each type of punishment.
  # These messages will be shown to the player when the punishment is applied.
  # You can use color codes by using "§" or "&" before the color code.
  # Note: Custom messages are only applicable when the `type` is not "none".
  messages:
    # Custom message for the "kick" punishment type.
    kick: '&cYou have been kicked for reaching the warning limit.'

    # Custom message for the "ban" punishment type.
    ban: '&cYou have been banned for reaching the warning limit.'
    
    # Custom message for the "ban-ip" punishment type.
    ban-ip: '&cYour IP address has been banned for reaching the warning limit.'

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

# Discord Integration
Use this awesome website to generate valid json with built-in preview: [Discohook](https://discohook.org/), also you can send webhooks to your server with it if you just wan't fancy embed in your channel without any automatization.

- Webhook on Discord
  1. Go to **Server settings** -> **Webhooks** -> **Create Webhook**
  1. Setup name, avatar and the channel, where it will be posted. Copy **Webhook URL**. **Do not share! Very dangerous!**
  1. Click **Save** and then the **Done** button

- Enable Discord Integration
  1. Open the **config.yml** file.
  2. Find the **discord** section and change **enabled** to **true**.
  3. Paste the webhook URL under **webhook_url**.

# Commands
The PlayerWarn plugin provides the following commands for chunk clearing:

- `/warn <player> <reason> [duration]`: Command to issue a warning to a player.
  - Permission: `playerwarn.command.warn`
- `/clearwarns <player>`: Command to clear all warnings for a player.
  - Permission: `playerwarn.command.warn`
- `/warns [player]`: Command to view all warnings.
  - Permission: `playerwarn.command.warn`

# Permissions
To control access to the commands provided by the PlayerWarn plugin, the following permissions are available:

- `playerwarn.command.warn`: Allows players to use the `/warn` command.
- `playerwarn.command.clearwarns`: Allows players to use the `/clearwarns` command.
- `playerwarn.command.warns`: Allows players to use the `/warns` command.

Grant these permissions to specific player groups or individuals using a permissions management plugin of your choice.

# Upcoming Features

- Currently none planned. You can contribute or suggest for new features.

# Additional Notes

- If you find bugs or want to give suggestions, please visit [here](https://github.com/AIPTU/PlayerWarn/issues).
- We accept all contributions! If you want to contribute, please make a pull request in [here](https://github.com/AIPTU/PlayerWarn/pulls).
- Icons made from [www.flaticon.com](https://www.flaticon.com)
